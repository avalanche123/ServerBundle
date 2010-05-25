<?php

namespace Bundle\ServerBundle\Handler\Http;

use Symfony\Components\HttpKernel\HttpKernelInterface,
    Symfony\Components\EventDispatcher\EventDispatcher,
    Symfony\Components\EventDispatcher\Event,
    Bundle\ServerBundle\Handler\HttpHandler,
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
class SymfonyHandler extends HttpHandler
{
    protected $kernel;
    protected $options;

    /**
     * @param KernelInterface $kernel
     */
    public function __construct(HttpKernelInterface $kernel, array $options)
    {
        $this->kernel  = $kernel;

        $this->options = array(
            'protocol'      => 'tcp',
            'address'       => '127.0.0.1',
            'port'          => 1962,
            'document_root' => $this->kernel->getRootDir().'/../web'
        );

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new \InvalidArgumentException(sprintf('The Server does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);
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

        // @TODO: maybe we should create & boot a brand new kernel, with a
        //        provided environment (see server:start -e) and w/o debug mode
        //        instead of using our "context" kernel?

        // handle request (main, raw)
        $sfResponse = $this->kernel->handle($sfRequest, HttpKernelInterface::MASTER_REQUEST, true);

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
