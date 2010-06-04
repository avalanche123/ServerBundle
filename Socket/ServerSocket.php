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
    protected $isIPv6;
    protected $address;
    protected $port;
    protected $maxClients;

    /**
     * @param string $address (optional)
     * @param integer $port (optional)
     * @param integer $maxClients (optional)
     *
     * @throws \InvalidArgumentException If address is not valid
     * @throws \InvalidArgumentException If port is not in valid range
     * @throws \RuntimeException If socket creation fails
     * @throws \RuntimeException If binding to socket fails
     * @throws \RuntimeException If listening to socket fails
     */
    public function __construct($address = '*', $port = 1962, $maxClients = 100)
    {
        parent::__construct();

        $this->isIPv6     = false;
        $this->address    = $address;
        $this->port       = $port;
        $this->maxClients = $maxClients;

        // convert wildcard address
        if ('*' == $this->address) {
            $this->address = '0.0.0.0';
        }

        // validate [IPv4/6] address
        if (false === filter_var($this->address, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException(sprintf('The address "%s" is not a valid IP address', $this->address));
        }

        // cover IPv6 address in braces
        if (true === filter_var($this->address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $this->isIPv6 = true;
        }

        // validate port number
        if (0 > $this->port || 65535 < $this->port) {
            throw new \InvalidArgumentException('The port number must range from 0 to 65535');
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
            throw new \RuntimeException(sprintf('Cannot listen to socket: %s', $this->getError()));
        }

        $this->connected  = true;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @return integer
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param integer $port
     *
     * @throws \InvalidArgumentException If the port number is not in range from 0 to 65535
     */
    public function setPort($port)
    {
        $this->port = $port;
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
