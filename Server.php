<?php

namespace Bundle\ServerBundle;

use Symfony\Components\DependencyInjection\ContainerInterface,
    Symfony\Components\HttpKernel\HttpKernelInterface,
    Symfony\Components\HttpKernel\Request,
    Symfony\Components\HttpKernel\Response;

/*
 * This file is part of the ServerBundle package.
 *
 * (c) Pierre Minnieur <pm@pierre-minnieur.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @package    ServerBundle
 * @subpackage Server
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class Server
{
  protected $container;
  protected $daemon;

  protected $address;
  protected $port;

  protected $maxClients;
  protected $maxRequests;
  protected $numRequests;

  protected $connected;
  protected $context;
  protected $socket;

  protected $run;
  protected $stop;

  protected $documentRoot;

  /**
   * @param ContainerInterface $container
   *
   * @throws \Exception If HTTP extension is not loaded
   */
  public function __construct(ContainerInterface $container)
  {
    if (!extension_loaded('http'))
    {
      throw new \Exception('HTTP extension not loaded.');
    }

    $this->container = $container;
    $this->daemon    = $this->container->getDaemonService();
    $this->context   = stream_context_create();

    $address      = $container->getParameter('server.address');
    $port         = $container->getParameter('server.port');

    $maxClients   = $container->getParameter('server.max_clients');
    $maxRequests  = $container->getParameter('server.max_requests_per_child');

    $documentRoot = $container->getParameter('server.document_root');

    if ('*' == $address)
    {
      $address = '0.0.0.0';
    }

    $this->address     = $address;
    $this->port        = $port;

    $this->maxClients  = $maxClients;
    $this->maxRequests = $maxRequests;
    $this->numRequests = 0;

    $this->run  = true;
    $this->stop = $this->daemon->isChild() ? false : true;

    $this->connected   = false;
    $this->socket      = null;

    $this->documentRoot = $documentRoot;

    // pcntl ticks
    declare(ticks = 1);

    // register pcntl signal handler
    pcntl_signal(SIGTERM, array($this, 'pcntl'));
  }

  public function pcntl($signo)
  {
    switch ($signo)
    {
      case SIGTERM:
        echo "SIGTERM RECIEVED\n\n";

        $this->stop = true;
        $this->run  = false;
        break;
    }
  }

  /**
   * @return string
   */
  public function getAddress()
  {
    return $this->address;
  }

  /**
   * @return integer
   */
  public function getPort()
  {
    return $this->port;
  }

  public function getMaxClients()
  {
    return $this->maxClients;
  }

  /**
   * @return integer
   */
  public function getMaxRequests()
  {
    return $this->maxRequests;
  }

  /**
   * @return integer
   */
  public function getNumRequests()
  {
    return $this->numRequests;
  }

  /**
   * @return string
   */
  public function getDocumentRoot()
  {
    return $this->documentRoot;
  }

  /**
   * @throws \Exception If the server is already started
   * @throws \Exception if the server cannot liston to the [address:port]
   */
  public function start()
  {
    // create server socket
    if (null === $this->socket)
    {
      $this->socket = @stream_socket_server('tcp://'.$this->getAddress().':'.$this->getPort(), $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $this->context);

      if (!$this->socket)
      {
        $error = 'Cannot listen to "tcp://%s:%d": %s';
        $error = sprintf($error, $this->getAddress(), $this->getPort(), $errstr);

        throw new \Exception($error, $errno);
      }

      // non blocking, w/o timeout
      stream_set_blocking($this->socket, false);
      stream_set_timeout($this->socket, 0);

      // state change
      $this->connected = true;
    }

    while (true === $this->run)
    {
      // accept socket
      $handler = @stream_socket_accept($this->socket);

      // socket timeout
      if (!$handler)
      {
        throw new \Exception('Cannot accept socket');
      }

      // read from socket
      $data = fread($handler, 16384);

      // empty data
      if (empty($data))
      {
        continue;
      }

      // debug informations
      $startTime   = microtime(true);
      $memoryUsage = memory_get_usage(true);

      // parse HTTP message
      $message = http_parse_message($data);

      // skip on non-requests
      if ($message->type != HTTP_MSG_REQUEST)
      {
        continue;
      }

      // increase request counter
      $this->numRequests++;

      $requestMethod   = $message->requestMethod;
      $requestUrl      = $message->requestUrl;
      $protocolVersion = $message->httpVersion;
      $headers         = $message->headers;

      // request data
      echo sprintf("REQUEST:\t%s %s HTTP/%s\n",
        $requestMethod, $requestUrl, $protocolVersion
      );

      // file exists in document root
      $file = sprintf('%s/%s',
        $this->documentRoot,
        ltrim($requestUrl, '/')
      );

      // check if file exists
      if (file_exists($file) && is_file($file))
      {
        // send static file
        $response = $this->sendFile($handler, $message, $file);
      }
      else
      {
        try
        {
          // send symfony response
          $response = $this->sendSymfony($handler, $message);
        }
        catch (\Exception $e)
        {
          // send server error
          $response = $this->sendError($handler, $message, $e);
        }
      }

      // split response data
      list($statusCode, $statusMessage, $bytesSend) = $response;

      // response data
      echo sprintf("RESPONSE:\tHTTP/%s %d %s\n\t\t%d bytes send\n",
        $protocolVersion, $statusCode, $statusMessage, $bytesSend
      );

      // runtime statistics
      echo sprintf("\t\t%.0f ms", (microtime(true) - $startTime) * 1000);
      echo sprintf("\t%.0f KB", (memory_get_usage(true) - $memoryUsage) / 1024);
      echo "\n\n";

      // lifecycle ends here
      if ($this->maxRequests > 0 && $this->numRequests >= $this->maxRequests)
      {
        $this->run = false;
      }
    }

    // runtime statistics
    echo sprintf("TIME:\t\t%.0f ms\n", (microtime(true) - $this->container->getKernelService()->getStartTime()) * 1000);
    echo sprintf("MEMORY:\t\t%.0f KB\n", memory_get_peak_usage(true)  / 1024);
    echo sprintf("REQUESTS:\t%d\n", $this->numRequests);
    echo "\n";

    $this->stop();

    // stop server
    if ($this->stop)
    {
      return true;
    }

    // create a new process
    if ($this->daemon->isChild())
    {
      $this->run  = true;
      $this->stop = $this->daemon->isChild() ? false : true;
      $this->numRequests = 0;

      return $this->daemon->restart();
    }
  }

  /**
   */
  public function stop()
  {
    if ($this->connected)
    {
      fclose($this->socket);
      $this->socket = null;

      $this->connected = false;
    }

    return true;
  }

  /**
   * @param resource $handler
   * @param stdClass $message
   * @param string $file
   * @return array
   */
  public function sendFile($handler, $message, $file)
  {
    $requestMethod   = $message->requestMethod;
    $requestUrl      = $message->requestUrl;
    $protocolVersion = $message->httpVersion;
    $headers         = $message->headers;

    // send HTTP message
    fwrite($handler, sprintf("HTTP/%s %d %s\r\n",
      $protocolVersion,
      $statusCode = 200,
      $statusMessage = Response::$statusTexts[$statusCode]
    ));

    // send Content-Length header
    fwrite($handler, sprintf("%s: %s\r\n", 'Content-Length', (string) $filesize = filesize($file)));

    /** @TODO send Content-Type header */

    // send content
    fwrite($handler, "\r\n");

    // send file
    $fp = fopen($file, 'r');
    while (!feof($fp))
    {
      fwrite($handler, fread($fp, 16384));
    }
    fclose($fp);

    return array($statusCode, $statusMessage, $filesize);
  }

  /**
   * @param resource $handler
   * @param stdClass $message
   * @return array
   */
  public function sendSymfony($handler, $message)
  {
    $requestMethod   = $message->requestMethod;
    $requestUrl      = $message->requestUrl;
    $protocolVersion = $message->httpVersion;
    $headers         = $message->headers;

    // fake $_SERVER array
    $server = array(
      'HTTP_HOST'       => $this->getAddress(),
      'SERVER_SOFTWARE' => 'Symfony 2',
      'SERVER_NAME'     => $this->getAddress(),
      'SERVER_ADDR'     => $this->getAddress(),
      'SERVER_PORT'     => $this->getPort(),
      'DOCUMENT_ROOT'   => $this->getDocumentRoot(),
      'SCRIPT_FILENAME' => '/index.php',
      'SERVER_PROTOCOL' => 'HTTP/'.$protocolVersion,
      'REQUEST_METHOD'  => $requestMethod,
      'QUERY_STRING'    => null,
      'REQUEST_URI'     => $requestUrl,
      'SCRIPT_NAME'     => '/index.php'
    );

    /** @var $kernel Symfony\Foundation\Kernel */
    $kernel = $this->container->getKernelService();

    if (!$kernel->isBooted())
    {
      $kernel->boot();
    }

    /**
     * @TODO extract _GET/_POST parameters, _COOKIE and _FILES
     * @link http://php.net/http_parse_params
     * @link http://php.net/http_parse_cookie
     *
     * @TODO check if _SESSION, _REQUEST and _ENV is needed
    */
    $parameters = $cookies = $files = array();

    // initialize HttpKernel\Request
    $request = Request::create($requestUrl, $requestMethod, $parameters, $cookies, $files, $server);

    /** @var $response Symfony\Components\HttpKernel\Response */
    $response = $kernel->handle($request, HttpKernelInterface::MASTER_REQUEST, true);

    // send HTTP message
    fwrite($handler, sprintf("HTTP/%s %d %s\r\n",
      $response->getProtocolVersion(),
      $statusCode = $response->getStatusCode(),
      $statusMessage = Response::$statusTexts[$statusCode]
    ));

    // set Date header
    $date = new \DateTime();
    $response->headers->set('Date', $date->format(DATE_RFC822));

    // set Content-Length header
    $content = $response->getContent();
    $response->headers->set('Content-Length', strlen($content));

    /**
     * @TODO add more headers
     * @link http://php.net/http_parse_headers
     */

    // send Response headers
    foreach ($response->headers->all() as $header => $value)
    {
      fwrite($handler, sprintf("%s: %s\r\n", $header, (string) $value));
    }

    // send content
    fwrite($handler, "\r\n");
    fwrite($handler, $content);

    // reboot kernel
    $kernel->shutdown();

    return array($statusCode, $statusMessage, strlen($content));
  }

  /**
   * @param resource $handler
   * @param stdClass $message
   * @param \Exception $e
   * @return array
   */
  public function sendError($handler, $message, \Exception $e)
  {
    $requestMethod   = $message->requestMethod;
    $requestUrl      = $message->requestUrl;
    $protocolVersion = $message->httpVersion;
    $headers         = $message->headers;

    // exception status
    echo "EXCEPTION:\t".$e->getMessage()."\n\n";

    // determine status code
    switch (get_class($e))
    {
      case 'Symfony\Components\HttpKernel\Exception\NotFoundHttpException':
        $statusCode = 404;
        break;
      default:
        $statusCode = 500;
        break;
    }

    // send HTTP message
    fwrite($handler, sprintf("HTTP/%s %d %s\r\n",
      $protocolVersion, $statusCode,
      $statusMessage = Response::$statusTexts[$statusCode]
    ));

    // build content
    $content = sprintf("<h1>%d - %s</h1>", $statusCode, $statusMessage);

    // send Content-Length header
    fwrite($handler, sprintf("%s: %s\r\n", 'Content-Length', strlen($content)));

    // send content
    fwrite($handler, "\r\n");
    fwrite($handler, $content);

    return array($statusCode, $statusMessage, strlen($content));
  }
}
