<?php

namespace Bundle\ServerBundle\Socket\Http;

use Bundle\ServerBundle\Socket\HttpSocket;

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
     * @return HttpMessage
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
     * @return \HttpMessage
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param HttpMessage $message
     *
     * @TODO move ClientSocket::doWrite() logic in here
     */
    public function sendResponse(\HttpMessage $message = null)
    {
        if (null !== $message) {
            $this->response = $message;
        }

        if (null === $this->response) {
            throw new \Exception('No response to send');
        }

        // write to socket
        $this->doWrite($this->response);
    }

    /**
     * @return \HttpMessage
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return boolean|HttpMessage
     */
    protected function doRead()
    {
        $data = $this->read();

        if (empty($data)) {
            return false;
        }

        // parse HTTP message
        $message = new \HttpMessage($data);

        // skip on non-requests
        if ($message->getType() != HTTP_MSG_REQUEST) {
            return false;
        }

        // @TODO keep-alive

        return $message;
    }

    /**
     * @param HttpMessage $message
     * @return integer
     */
    protected function doWrite(\HttpMessage $message)
    {
        $buffer = sprintf("HTTP/%s %d %s\r\n",
            $message->getHttpVersion(),
            $message->getResponseCode(),
            $message->getResponseStatus()
        );

        foreach ($message->getHeaders() as $header => $value) {
            $buffer .= sprintf("%s: %s\r\n",
                $header,
                $value
            );
        }

        $buffer .= "\r\n";
        $buffer .= $message->getBody();
        $buffer .= "\r\n";

        return $this->write($buffer);
    }

    /**
     */
    public function timer()
    {
        // keep alive check
    }
}
