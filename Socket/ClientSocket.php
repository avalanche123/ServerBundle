<?php

namespace Bundle\ServerBundle\Socket;

use Bundle\ServerBundle\Socket\Socket,
    Bundle\ServerBundle\Request,
    Bundle\ServerBundle\Response,
    Symfony\Foundation\Kernel,
    Bundle\ServerBundle\Bundle;

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
 * @subpackage Socket
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class ClientSocket extends Socket
{
    protected $server;
    protected $accepted;
    protected $keepAlive;
    protected $lastAction;
    protected $options;
    protected $request;
    protected $response;
    protected $timeout;
    protected $keepAliveTimeout;

    /**
     * @param ServerSocket $server
     * @param integer $timeout (optional)
     * @param integer $keepAliveTimeout (optional)
     */
    public function __construct(ServerSocket $server, $timeout = 90, $keepAliveTimeout = 15)
    {
        $this->server           = $server;
        $this->request          = null;
        $this->response         = null;
        $this->timeout          = $timeout;
        $this->keepAliveTimeout = $keepAliveTimeout;
        $this->keepAlive        = false;
        $this->accepted         = time();
        $this->lastAction       = $this->accepted;

        parent::__construct($this->server->accept());

        // set timeout
        $this->setTimeout($this->timeout);
    }

    /**
     * @return Request
     */
    public function readRequest()
    {
        if (null !== $this->request) {
            return $this->request;
        }

        $this->lastAction = time();

        $message = $this->read();
        $message = trim($message);

        if (empty($message)) {
            $this->disconnect();
            return false;
        }

        try {
            // parse HTTP message
            $request = new Request($message);
        } catch (\InvalidArgumentException $e) {
            // @TODO add 400 (Bad Request) response
            $this->disconnect();
            return false;
        }

        // Connection: Keep-Alive check
        $httpVersion = $request->getHttpVersion();
        $connection  = strtolower($request->getHeader('Connection'));
        if ((Request::HTTP_11 == $httpVersion && 'close' != $connection) ||
            (Request::HTTP_10 == $httpVersion && 'keep-alive' == $connection)) {
            $this->keepAlive = true;
        }

        return $this->request = $request;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param Response $response
     */
    public function sendResponse(Response $response = null)
    {
        if (null !== $response) {
            $this->response = $response;
        }

        if (null === $this->response) {
            $this->disconnect();
            return false;
        }

        $response = $this->response;

        // Connection: Keep-Alive check
        if (true === $this->keepAlive) {
            $response->setHeader('Connection', 'Keep-Alive');
            $response->setHeader('Keep-Alive', sprintf('timeout=%d, max=%d',
                $this->keepAliveTimeout, $this->timeout
            ));
        } else {
            $response->setHeader('Connection', 'close');
        }

        // Content-MD5 integrity check
        $response->setHeader('Content-MD5', md5($response->getBody()));

        // Server and Via header
        $response->setHeader('Server', sprintf('Symfony/%s (ServerBundle)', Kernel::VERSION));
        $response->setHeader('Via', sprintf('ServerBundle/%s', Bundle::VERSION));

        $this->request  = null;
        $this->response = null;

        $send = $this->write($response->toString());

        if (!$this->keepAlive) {
            $this->disconnect();
        }

        // write to socket
        return $send;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     */
    public function timer()
    {
        $idle  = time() - $this->lastAction;
        $total = time() - $this->accepted;

        if ($total > $this->timeout || $idle > $this->keepAliveTimeout) {
            $this->disconnect();
        }
    }

    /**
     * @return integer
     */
    public function getAccepted()
    {
        return $this->accepted;
    }

    /**
     * @return integer
     */
    public function getLastAction()
    {
        return $this->lastAction;
    }

    /**
     * @return boolean
     */
    public function isKeepAlive()
    {
        return $this->keepAlive;
    }
}
