<?php

namespace Bundle\ServerBundle\Socket;

use Bundle\ServerBundle\Socket\Socket;

class ServerSocket extends Socket
{
    protected $address;
    protected $port;
    protected $realAddress;

    /**
     * @param string $address
     * @param integer $port
     *
     * @throws \InvalidArgumentException If the address is not a valid IP address
     * @throws \InvalidArgumentException If the port number is not in range from 0 to 65535
     */
    public function __construct($address, $port)
    {
        parent::__construct();

        $this->address = $address;
        $this->port    = $port;

        if ('*' == $this->address) {
            $this->address = '0.0.0.0';
        }

        // validate [IPv4/6] address
        if (false === filter_var($this->address, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException(sprintf('The address "%s" is not a valid IP address', $this->address));
        }

        // cover IPv6 address in braces
        if (true === filter_var($this->address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $this->address = sprintf('[%s]', $this->address);
        }

        // validate port number
        if (0 > $this->port || 65535 < $this->port) {
            throw new \InvalidArgumentException('The port number must range from 0 to 65535');
        }

        $this->realAddress = sprintf('tcp://%s:%d', $this->address, $this->port);
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
     * @return string
     */
    public function getRealAddress()
    {
        return $this->realAddress;
    }

    /**
     * @return boolean
     *
     * @throws \RuntimeException If socket cannot be created
     */
    public function connect()
    {
        $this->socket = @stream_socket_server($this->realAddress, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $this->context);

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
