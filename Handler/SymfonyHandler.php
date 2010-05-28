<?php

namespace Bundle\ServerBundle\Handler;

use Bundle\ServerBundle\Handler\HandlerInterface,
    Symfony\Components\EventDispatcher\EventDispatcher,
    Symfony\Components\EventDispatcher\Event,
    Symfony\Components\HttpKernel\HttpKernelInterface,
    Symfony\Foundation\Kernel,
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
 * @subpackage Handler
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class SymfonyHandler implements HandlerInterface
{
    protected $kernel;
    protected $customKernel;
    protected $options;

    /**
     * @param KernelInterface $kernel
     */
    public function __construct(HttpKernelInterface $kernel, array $options)
    {
        $this->kernel       = $kernel;
        $this->customKernel = null;

        $this->options = array(
            'kernel_environment' => 'dev',
            'kernel_debug'       => true,
            'protocol'           => 'tcp',
            'address'            => '127.0.0.1',
            'port'               => 1962,
            'document_root'      => $this->kernel->getRootDir().'/../web'
        );

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new \InvalidArgumentException(sprintf('The Server does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);

        // start a custom kernel if needed
        if ($this->options['kernel_environment'] != $this->kernel->getEnvironment() ||
            $this->options['kernel_debug'] != $this->kernel->isDebug()) {
            $class = get_class($kernel);
            $this->customKernel = new $class($this->options['kernel_environment'], $this->options['kernel_debug']);
            $this->customKernel->boot();
        }
    }

    /**
     * @param EventDispatcher $dispatcher
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('server.request', array($this, 'handle'));
    }

    /**
     * @param Event $event
     *
     * @see EventDispatcher::notifyUntil()
     */
    public function handle(Event $event)
    {
        // get HttpMessage request
        $request = $event->getSubject();

        $requestMethod = $request->getRequestMethod();
        $requestUrl    = $request->getRequestUrl();

        // fake _SERVER environment
        $server = array(
            'HTTP_HOST'       => $this->options['address'],
            'SERVER_SOFTWARE' => 'Symfony '.Kernel::VERSION,
            'SERVER_NAME'     => $this->options['address'],
            'SERVER_ADDR'     => $this->options['address'],
            'SERVER_PORT'     => $this->options['port'],
            'DOCUMENT_ROOT'   => $this->options['document_root'],
            'SCRIPT_FILENAME' => '/index.php',
            'SERVER_PROTOCOL' => 'HTTP/'.$request->getHttpVersion(),
            'REQUEST_METHOD'  => $requestMethod,
            'QUERY_STRING'    => null,
            'REQUEST_URI'     => $requestUrl,
            'SCRIPT_NAME'     => '/index.php'
        );

        // @TODO: fake _GET, _POST, _REQUEST, _COOKIE, _FILES
        $parameters = $cookies = $files = array();

        // @TODO: what's up w/ _SESSION?

        // initialize HttpKernel\Request
        $sfRequest = Request::create($requestUrl, $requestMethod, $parameters, $cookies, $files, $server);

        try
        {
            if (null !== $this->customKernel) {
                $sfResponse = $this->customKernel->handle($sfRequest);
            } else {
                $sfResponse = $this->kernel->handle($sfRequest);
            }
        } catch (\Exception $e) {
            $code    = 500;
            $status  = Response::$statusTexts[$code];
            $headers = array();
            $content = sprintf('<h1>Error %d - %s</h1>', $code, $status);
            // ExceptionController-like view renderer would be cool

            // add Date header
            $date = new \DateTime();
            $headers['Date'] = $date->format(DATE_RFC822);

            // add Content-Length header
            $headers['Content-Length'] = strlen($content);

            // build HttpMessage response
            $response = new \HttpMessage();
            $response->setHttpVersion($request->getHttpVersion());
            $response->setType(HTTP_MSG_RESPONSE);
            $response->setResponseCode($code);
            $response->setResponseStatus($status);
            $response->addHeaders($headers);
            $response->setBody($content);

            $event->setReturnValue($response);

            return true;
        }

        // add Date header
        $date = new \DateTime();
        $sfResponse->headers->set('Date', $date->format(DATE_RFC822));

        // add Content-Length header
        $sfContent = $sfResponse->getContent();
        $sfResponse->headers->set('Content-Length', strlen($sfContent));

        // build HttpMessage response
        $response = new \HttpMessage();
        $response->setType(HTTP_MSG_RESPONSE);
        $response->setHttpVersion($sfResponse->getProtocolVersion());
        $response->setResponseCode($code = $sfResponse->getStatusCode());
        $response->setResponseStatus(Response::$statusTexts[$code]);
        $response->addHeaders($sfResponse->headers->all());
        $response->setBody($sfContent);

        $event->setReturnValue($response);

        return true;
    }
}
