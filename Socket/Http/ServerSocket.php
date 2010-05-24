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
class ServerSocket extends HttpSocket
{
    protected $socketClientClass;

    /**
     * @param string $socketClientClass
     * @param string $protocol
     * @param string $address
     * @param integer $port
     */
    public function __construct($socketClientClass, $protocol, $address, $port)
    {
        parent::__construct($protocol, $address, $port);

        $this->socketClientClass = $socketClientClass;
    }

    /**
     * @return boolean
     */
    public function connect()
    {
        $this->socket = @stream_socket_server($this->realAddress, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $this->context);

        if (false === $this->socket) {
            throw new \Exception(sprintf('Cannot create socket: %s', $errstr), $errno);
        }

        $this->connected = true;
        $this->setBlocking(false);
        $this->setTimeout(0);

        return true;
    }

    /**
     * @return Bundle\ServerBundle\Socket\Http\ClientSocket
     */
    public function accept()
    {
        $socket = @stream_socket_accept($this->socket);

        if (false === $socket) {
            throw new \Exception('Cannot accept socket');
        }

        $client = new $this->socketClientClass($socket);

        return $client;
    }
}
