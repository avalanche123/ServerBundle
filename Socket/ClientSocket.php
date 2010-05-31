<?php

namespace Bundle\ServerBundle\Socket;

use Bundle\ServerBundle\Socket\Socket,
    Bundle\ServerBundle\Request,
    Bundle\ServerBundle\Response,
    Symfony\Foundation\Kernel,
    Bundle\ServerBundle\Bundle;


class ClientSocket extends Socket
{
    protected $accepted;
    protected $keepAlive;
    protected $lastAction;

    protected $timeout;
    protected $keepAliveTimeout;

    protected $request;
    protected $response;

    /**
     * @param resource $socket
     */
    public function __construct($socket, $timeout = 90, $keepAliveTimeout = 15)
    {
        parent::__construct($socket);

        $this->request  = null;
        $this->response = null;

        // connection keep alive
        $this->accepted   = time();
        $this->keepAlive  = false;
        $this->lastAction = $this->accepted;

        $this->timeout = $timeout;
        $this->keepAliveTimeout = $keepAliveTimeout;

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
            return false;
        }

        try {
            // parse HTTP message
            $request = new Request($message);
        } catch (\InvalidArgumentException $e) {
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
     * @param HttpMessage $message
     *
     * @throws \InvalidArgumentException If there is no Response to send
     */
    public function sendResponse(Response $response = null)
    {
        if (null !== $response) {
            $this->response = $response;
        }

        if (null === $this->response && !$this->keepAlive) {
            return $this->disconnect();
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

        $response->setHeader('Server', 'Symfony '.Kernel::VERSION);
        $response->setHeader('Via', 'ServerBundle '.Bundle::VERSION);

        $this->request  = null;
        $this->response = null;

        // write to socket
        return $this->write($response->toString());
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
