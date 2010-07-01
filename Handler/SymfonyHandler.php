<?php

namespace Bundle\ServerBundle\Handler;

use Bundle\ServerBundle\Handler\Handler,
    Symfony\Components\DependencyInjection\ContainerInterface,
    Bundle\ServerBundle\Request,
    Bundle\ServerBundle\Response,
    Symfony\Components\EventDispatcher\EventDispatcher,
    Symfony\Components\EventDispatcher\Event,
    Symfony\Components\HttpKernel\HttpKernelInterface,
    Symfony\Foundation\Kernel,
    Symfony\Components\HttpKernel\Request as SymfonyRequest,
    Symfony\Components\HttpKernel\Response as SymfonyResponse;

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
class SymfonyHandler extends Handler
{
    protected $kernel;
    protected $customKernel;
    protected $options;

    /**
     * @param Symfony\Components\DependencyInjection\ContainerInterface $container
     * @param Symfony\Components\HttpKernel\HttpKernelInterface $kernel
     * @param array $options (optional)
     */
    public function __construct(ContainerInterface $container, HttpKernelInterface $kernel, array $options)
    {
        parent::__construct($container);

        $this->kernel       = $kernel;
        $this->customKernel = null;

        $this->options = array(
            'kernel_environment' => 'dev',
            'kernel_debug'       => true,
            'hostname'           => 'localhost',
            'admin'              => 'root@localhost',
            'hostname_lookups'   => false,
            'document_root'      => $this->kernel->getRootDir().'/../web'
        );

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new \InvalidArgumentException(sprintf('The Server does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);

        // realpath document root
        $this->options['document_root'] = realpath($this->options['document_root']);

        // start a custom kernel if needed
        if ($this->options['kernel_environment'] != $this->kernel->getEnvironment() ||
            $this->options['kernel_debug'] != $this->kernel->isDebug()) {
            $class = get_class($kernel);
            $this->customKernel = new $class($this->options['kernel_environment'], $this->options['kernel_debug']);
            $this->customKernel->boot();
        }
    }

    /**
     * @param Symfony\Components\EventDispatcher\EventDispatcher $dispatcher
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('server.request', array($this, 'handle'));
    }

    /**
     * @param Symfony\Components\EventDispatcher\Event $event
     *
     * @see Symfony\Components\EventDispatcher\EventDispatcher::notifyUntil()
     */
    public function handle(Event $event)
    {
        /** @var Bundle\ServerBundle\RequestInterface $request */
        $request = $event->getSubject();
        /** @var Bundle\ServerBundle\Socket\ServerSocket $server */
        $server  = $event->getParameter('server');
        /** @var Bundle\ServerBundle\Socket\ClientSocket $client */
        $client  = $event->getParameter('client');

        // collect parameters
        $requestMethod  = $request->getRequestMethod();
        $requestUrl     = $request->getRequestUrl();
        $url            = parse_url($requestUrl);
        $queryString    = isset($url['path']) ? $url['path'] : null;
        // @TODO: fix script name, script filename & path_translated
        $scriptName     = '/index.php';

        // GET & POST parameters
        $getParameters  = array();
        parse_str($queryString, $getParameters);

        $postParameters = array();
        if ($request->getRequestMethod() == Request::METHOD_POST) {
            parse_str($request->getBody(), $postParameters);
        }

        // local & remote address:port
        list($address, $port)             = explode(':', $server->getName());
        list($remoteAddress, $remotePort) = explode(':', $client->getPeerName());

        // fake _SERVER
        // @see http://php.net/manual/de/reserved.variables.server.php
        $server = array(
            'SERVER_SIGNATURE'     => sprintf('<address>Symfony/%s (ServerBundle) Server at %s Port %d</address>', Kernel::VERSION, $this->options['hostname'], $port),
            'SERVER_SOFTWARE'      => sprintf('Symfony/%s (ServerBundle)', Kernel::VERSION),
            'SERVER_NAME'          => $this->options['hostname'],
            'SERVER_ADDR'          => $address,
            'SERVER_PORT'          => $port,
            'SERVER_ADMIN'         => $this->options['admin'],
            'GATEWAY_INTERFACE'    => 'CGI/1.1',
            'SERVER_PROTOCOL'      => 'HTTP/'.$request->getHttpVersion(),
            'REQUEST_METHOD'       => $requestMethod,
            'REMOTE_ADDR'          => $remoteAddress,
            'REMOTE_PORT'          => $remotePort,
            'DOCUMENT_ROOT'        => $this->options['document_root'],
            'QUERY_STRING'         => $queryString,
            'REQUEST_URI'          => $requestUrl,
            'REQUEST_TIME'         => $client->getAccepted(),
            'PHP_SELF'             => $url['path'],
            'SCRIPT_NAME'          => $scriptName,
            'SCRIPT_FILENAME'      => $scriptFilename = $this->options['document_root'].$scriptName,
            'PATH_INFO'            => str_replace($scriptName, '', $url['path']),
            'PATH_TRANSLATED'      => $scriptFilename
        );

        // @TODO: PATH
        // @TODO: AUTH > AUTH_TYPE, REMOTE_USER, REMOTE_IDENT
        // @TODO: POST, PUT > CONTENT_TYPE, CONTENT_LENGTH

        // extend _SERVER
        if ($request->getRequestMethod() == Request::METHOD_GET) {
            $server['argv'] = $queryString;
            $server['argc'] = count($getParameters);
        }
        if ($this->options['hostname_lookups']) {
            $server['REMOTE_HOST'] = gethostbyaddr($remoteAddress);
        }
        if ($request->hasHeader('Host')) {
            $server['HTTP_HOST'] = $request->getHeader('Host');
        }
        if ($request->hasHeader('Connection')) {
            $server['HTTP_CONNECTION'] = $request->getHeader('Connection');
        }
        if ($request->hasHeader('User-Agent')) {
            $server['HTTP_USER_AGENT'] = $request->getHeader('User-Agent');
        }
        if ($request->hasHeader('Accept')) {
            $server['HTTP_ACCEPT'] = $request->getHeader('Accept');
        }
        if ($request->hasHeader('Accept-Encoding')) {
            $server['HTTP_ACCEPT_ENCODING'] = $request->getHeader('Accept-Encoding');
        }
        if ($request->hasHeader('Accept-Language')) {
            $server['HTTP_ACCEPT_LANGUAGE'] = $request->getHeader('Accept-Language');
        }
        if ($request->hasHeader('Accept-Charset')) {
            $server['HTTP_ACCEPT_CHARSET'] = $request->getHeader('Accept-Charset');
        }
        if ($request->hasHeader('Cookie')) {
            $server['HTTP_COOKIE'] = $request->getHeader('Cookie');
        }
        if ($request->hasHeader('Referer')) {
            $server['HTTP_REFERER'] = $request->getHeader('Referer');
        }

        // fake _COOKIE
        $cookies = array();
        if ($request->hasHeader('Cookie')) {
            parse_str($request->getHeader('Cookie'), $cookies);
        }

        // @TODO: php.ini - request_order > _REQUEST
        $parameters = array_merge($getParameters, $postParameters);

        // @TODO: fake _FILES
        $files = array();

        // initialize SymfonyRequest
        $sfRequest = SymfonyRequest::create($requestUrl, $requestMethod, $parameters, $cookies, $files, $server);

        try
        {
            if (null !== $this->customKernel) {
                /** @var $sfResponse Symfony\Components\HttpKernel\Response */
                $sfResponse = $this->customKernel->handle($sfRequest);
            } else {
                /** @var $sfResponse Symfony\Components\HttpKernel\Response */
                $sfResponse = $this->kernel->handle($sfRequest);
            }
        } catch (\Exception $e) {
            $code    = 500;
            $status  = SymfonyResponse::$statusTexts[$code];
            $headers = array();
            $content = sprintf('<h1>Error %d - %s</h1>', $code, $status);
            // ExceptionController-like view renderer would be cool

            // add Date header
            $date = new \DateTime();
            $headers['Date'] = $date->format(DATE_RFC822);

            // add Content-Length header
            $headers['Content-Length'] = strlen($content);

            // build Response
            $response = $this->container->getServer_ResponseService();
            $response->setHttpVersion($request->getHttpVersion());
            $response->setStatusCode($code, $status);
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

        // build Response
        $response = $this->container->getServer_ResponseService();
        $response->setHttpVersion($sfResponse->getProtocolVersion());
        $response->setStatusCode($sfResponse->getStatusCode());
        $response->addHeaders($sfResponse->headers->all());
        $response->setBody($sfContent);

        $event->setReturnValue($response);

        return true;
    }
}
