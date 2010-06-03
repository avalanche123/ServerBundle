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
    protected $maxClients;

    /**
     * @param integer $maxClients
     */
    public function __construct($maxClients = 100)
    {
        parent::__construct();

        $this->maxClients = $maxClients;
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

        $this->socket = @socket_create($this->isIPv6 ? AF_INET6 : AF_INET, SOCK_STREAM, SOL_TCP);

        if (false === $this->socket) {
            throw new \RuntimeException(sprintf('Cannot create socket: %s', $this->getError()));
        }

        $this->setBlocking(false);
        $this->setTimeout(0);
        $this->setReuseAddress(true);

        if (false === @socket_bind($this->socket, $this->address, $this->port)) {
            throw new \RuntimeException(sprintf('Cannot bind to socket: %s', $this->getError()));
        }

        if (false === @socket_listen($this->socket, $this->maxClients)) {
            throw new \RuntimeException(sprintf('Cannot bind to socket: %s', $this->getError()));
        }

        $this->connected = true;

        return true;
    }

    /**
     * @return resource
     *
     * @throws \RuntimeException If socket cannot be accepted
     */
    public function accept()
    {
        $socket = @socket_accept($this->socket);

        if (false === $socket) {
            throw new \RuntimeException(sprintf('Cannot accept socket: %s', $this->getError()));
        }

        return $socket;
    }
}
