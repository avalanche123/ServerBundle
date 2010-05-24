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
 * @subpackage Handler
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class ClientSocket extends HttpSocket
{
    protected $accepted;

    /**
     * @return boolean
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

        return true;
    }

    /**
     * @return HttpMessage
     */
    public function read()
    {
        $data = parent::read();

        if (empty($data)) {
            return false;
        }

        // parse HTTP message
        $message = new HttpMessage($data);

        // skip on non-requests
        if ($message->getType() != HTTP_MSG_REQUEST) {
            return false;
        }

        // @TODO keep-alive

        return $message;
    }

    /**
     * @param HttpMessage $message
     */
    public function write(HttpMessage $message)
    {
        return parent::write($message->toString());
    }

    public function onTimer()
    {
        // keep alive check
    }
}
