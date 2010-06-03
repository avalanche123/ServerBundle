<?php

namespace Bundle\ServerBundle\Socket;

use Bundle\ServerBundle\Socket\SocketInterface;

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
abstract class Socket implements SocketInterface
{
    // protected $context;
    protected $socket;
    protected $connected;
    protected $blocked;
    protected $isIPv6;
    protected $address;
    protected $port;

    /**
     * @return void
     */
    public function __construct($socket = null)
    {
        $this->socket    = null;
        $this->isIPv6    = false;
        $this->address   = '0.0.0.0';
        $this->port      = 1962;
        $this->connected = false;
        $this->blocked   = false;

        if (null !== $socket) {
            if (!is_resource($socket)) {
                throw new \InvalidArgumentException('Socket must be a valid resource');
            }

            $this->socket    = $socket;
            $this->connected = true;
            $this->setBlocking(false);
            $this->setTimeout(0);
            $this->setReuseAddress(true);
        }
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @return string
     */
    public function getError()
    {
        $error = socket_strerror(socket_last_error($this->socket));

        socket_clear_error($this->socket);

        return $error;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param $address
     *
     * @throws \InvalidArgumentException If the address is not a valid IP address
     */
    public function setAddress($address)
    {
        $this->address = $address;

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
            // $this->address = sprintf('[%s]', $this->address);
        }
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

        // validate port number
        if (0 > $this->port || 65535 < $this->port) {
            throw new \InvalidArgumentException('The port number must range from 0 to 65535');
        }
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return (integer) $this->socket;
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @return boolean
     */
    public function isConnected()
    {
        return $this->connected;
    }

    /**
     * @return boolean
     */
    public function isWaiting()
    {
        return $this->blocked;
    }

    /**
     * @return void
     */
    public function disconnect()
    {
        if ($this->connected && is_resource($this->socket)) {
            @socket_shutdown($this->socket, 2);
            socket_close($this->socket);
        }

        $this->connected = false;
    }

    /**
     * @param boolean $blocking (optional)
     * @return void
     */
    public function setBlocking($blocking = false)
    {
        if (true === $blocking) {
            return socket_set_block($this->socket);
        }

        return socket_set_nonblock($this->socket);
    }

    /**
     * @param integer $seconds
     * @param integer $microseconds (optional)
     * @return void
     */
    public function setTimeout($seconds, $microseconds = 0)
    {
        return $this->setOption(SO_RCVTIMEO, array(
            'sec'  => $seconds,
            'usec' => $microseconds
        ));
    }

    /**
     * @param boolean $reuse (optional)
     * @return boolean
     */
    public function setReuseAddress($reuse = true)
    {
        return $this->setOption(SO_REUSEADDR, $reuse);
    }

    /**
     * @param string $option
     * @param integer $level (optional)
     * @return array
     */
    public function getOption($option, $level = SOL_SOCKET)
    {
        return socket_get_option($this->socket, $level, $option);
    }

    /**
     * @param string $option
     * @param mixed $value
     * @param integer $level (optional)
     * @return boolean
     */
    public function setOption($option, $value, $level = SOL_SOCKET)
    {
        return socket_set_option($this->socket, $level, $option, $value);
    }

    /**
     * @param integer $length (optional)
     * @return string
     */
    public function read($length = 16384)
    {
        return socket_read($this->socket, $length, PHP_BINARY_READ );
    }

    /**
     * @param mixed $data
     * @return integer
     */
    public function write($data)
    {
        $send = socket_write($this->socket, $data, $length = strlen($data));

        if ($send != $length) {
            $this->blocked = true;
        }

        return $send;
    }
}
