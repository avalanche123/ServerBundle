<?php

namespace Bundle\ServerBundle\Socket;

use Bundle\ServerBundle\Socket\Socket;

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
class ServerSocket extends Socket
{
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

        $this->socket = @stream_socket_server($this->getRealAddress(), $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $this->context);

        if (false === $this->socket) {
            throw new \RuntimeException(sprintf('Cannot create socket: %s', $errstr), $errno);
        }

        $this->connected = true;
        $this->setBlocking(false);
        $this->setTimeout(0);

        return true;
    }

    /**
     * @return resource
     *
     * @throws \RuntimeException If socket cannot be accepted
     */
    public function accept()
    {
        $socket = @stream_socket_accept($this->socket);

        if (false === $socket) {
            throw new \RuntimeException('Cannot accept socket');
        }

        return $socket;
    }
}
