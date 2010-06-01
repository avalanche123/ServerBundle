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

    /**
     * @param resource $socket
     */
    public function __construct($socket, array $options = array())
    {
        parent::__construct($socket);

        $this->request    = null;
        $this->response   = null;
        $this->accepted   = time();
        $this->lastAction = $this->accepted;

        // @see Resources/config/server.xml
        $this->options = array(
            'address'           => '*',
            'port'              => 1962,
            'timeout'           => 90,
            'keepalive_timeout' => 15
        );

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new \InvalidArgumentException(sprintf('The Server does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);

        // set timeout
        $this->setTimeout($this->options['timeout']);
    }

    /**
     * @return boolean
     *
     * @throws \InvalidArgumentException If address is not set
     * @throws \InvalidArgumentException If port is not set
     * @throws \RuntimeException If socket cannot be created
     */
    public function connect($address = null, $port = null)
    {
        if (null !== $address) {
            $this->setAddress($address);
        }

        if (null !== $port) {
            $this->setPort($port);
        }

        if (null === $this->address) {
            throw new \InvalidArgumentException('Address must be set');
        }

        if (null === $this->port) {
            throw new \InvalidArgumentException('Port must be set');
        }

        $this->socket = @stream_socket_client($this->getRealAddress(), $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $this->context);

        if (false === $this->socket) {
            throw new \RuntimeException(sprintf('Cannot create socket: %s', $errstr), $errno);
        }

        $this->connected = true;
        $this->setBlocking(false);
        $this->setTimeout($this->options['timeout']);

        return true;
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
     * @param Response $response
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
                $this->options['keepalive_timeout'], $this->options['timeout']
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

        // write to socket
        return parent::write($response->toString());
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

        if ($total > $this->options['timeout'] || $idle > $this->options['keepalive_timeout']) {
            $this->disconnect();
        }
    }
}
