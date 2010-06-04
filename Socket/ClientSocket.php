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
    protected $accepted;
    protected $keepAlive;
    protected $lastAction;
    protected $options;
    protected $request;
    protected $response;
    protected $timeout;
    protected $keepAliveTimeout;

    /**
     * @param resource $socket
     * @param integer $timeout (optional)
     * @param integer $keepAliveTimeout (optional)
     */
    public function __construct($socket, $timeout = 90, $keepAliveTimeout = 15)
    {
        parent::__construct($socket);

        $this->timeout          = $timeout;
        $this->keepAliveTimeout = $keepAliveTimeout;
        $this->request          = null;
        $this->response         = null;
        $this->accepted         = time();
        $this->lastAction       = $this->accepted;
        $this->keepAlive        = false;

        // set timeout
        $this->setTimeout($this->timeout);
    }

    /**
     * @return Request
     */
    public function readResponse()
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
            $response->setHeader('Keep-Alive', sprintf('timeout=%d max=%d',
                $this->keepAliveTimeout, $this->timeout
            ));
        } else {
            $response->setHeader('Connection', 'close');
        }

        // Content-MD5 integrity check
        $response->setHeader('Content-MD5', md5($response->getBody()));

        // Server and Via header
        $response->setHeader('Server', 'Symfony '.Kernel::VERSION);
        $response->setHeader('Via', 'ServerBundle '.Bundle::VERSION);

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
}
