<?php

namespace Bundle\ServerBundle\Socket\Http;

use Bundle\ServerBundle\Socket\HttpSocket,
    Bundle\ServerBundle\Request,
    Bundle\ServerBundle\Response;

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
class ClientSocket extends HttpSocket
{
    protected $accepted;
    protected $request;
    protected $response;

    /**
     * @return boolean
     *
     * @throws \Exception If socket cannot be created
     */
    public function connect()
    {
        $this->socket = @stream_socket_client($this->realAddress, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $this->context);

        if (false === $this->socket) {
            throw new \Exception(sprintf('Cannot create socket: %s', $errstr), $errno);
        }

        $this->connected = true;
        $this->setBlocking(false);
        $this->setTimeout(0);

        // keep alive
        $this->accepted = time();

        $this->request  = null;
        $this->response = null;

        return true;
    }

    /**
     * @return Request
     *
     * @TODO move ClientSocket::doRead() logic in here
     */
    public function readRequest()
    {
        if (null !== $this->request) {
            return $this->request;
        }

        // read from socket
        $this->request = $this->doRead();

        return $this->request;
    }

    /**
     * @return boolean|Request
     */
    protected function doRead()
    {
        $message = $this->read();
        $message = trim($message);

        if (empty($message)) {
            return false;
        }

        // parse HTTP message
        try {
            $request = new Request($message);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        // @TODO keep-alive

        return $request;
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
     *
     * @TODO move ClientSocket::doWrite() logic in here
     */
    public function sendResponse(Response $response = null)
    {
        if (null !== $response) {
            $this->response = $response;
        }

        if (null === $this->response) {
            throw new \InvalidArgumentException('No Response to send');
        }

        $response = $this->response;

        $this->request  = null;
        $this->response = null;

        // write to socket
        return $this->doWrite($response);
    }

    /**
     * @param Response $response
     * @return integer
     */
    protected function doWrite(Response $response)
    {
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
        // keep alive check
    }
}
