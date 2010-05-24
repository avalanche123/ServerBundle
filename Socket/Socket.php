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
    protected $context;
    protected $socket;
    protected $connected;
    protected $blocked;

    /**
     * @return void
     */
    public function __construct($socket = null)
    {
        if (null === $socket) {
            $this->context   = stream_context_create();
            $this->connected = false;
        } else {
            if (!is_resource($socket)) {
                throw new \Exception('Socket must be a valid resource');
            }

            $this->socket    = $socket;
            $this->connected = true;
            $this->setBlocking(false);
            $this->setTimeout(0);
        }

        $this->blocked = false;
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
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
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            fclose($this->socket);
        }

        $this->connected = false;
    }

    /**
     * @param boolean $blocking (optional)
     * @return void
     */
    public function setBlocking($blocking = false)
    {
        stream_set_blocking($this->socket, $blocking);
    }

    /**
     * @param integer $seconds
     * @param integer $microseconds (optional)
     * @return void
     */
    public function setTimeout($seconds, $microseconds = 0)
    {
        stream_set_timeout($this->socket, $seconds, $microseconds);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return stream_socket_get_name($this->socket, false);
    }

    /**
     * @return string
     */
    public function getPeerName()
    {
        return stream_socket_get_name($this->socket, true);
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        if (null !== $this->socket) {
            return stream_context_get_options($this->socket);
        }

        return stream_context_get_options($this->context);
    }

    /**
     * @param string $wrapper
     * @param string$option
     * @param mixed $value
     * @return boolean
     */
    public function setOption($wrapper, $option, $value)
    {
        if (null !== $this->socket) {
            return stream_context_set_option($this->socket, $wrapper, $option, $value);
        }

        return stream_context_set_option($this->context, $wrapper, $option, $value);
    }

    /**
     * @param integer $length (optional)
     * @return string
     */
    public function read($length = 16384)
    {
        return fread($this->socket, $length);
    }

    /**
     * @param string $address
     * @param integer $length (optional)
     * @return string
     */
    public function readFrom($address, $length = 16384)
    {
        return stream_socket_recvfrom($this->socket, $length, null, $address);
    }

    /**
     * @param mixed $data
     * @return integer
     */
    public function write($data)
    {
        $send = frwite($this->socket, $data);

        if ($send != strlen($data)) {
            $this->blocked = true;
        }

        return $send;
    }

    /**
     * @param string $address
     * @param mixed $data
     * @return integer
     */
    public function writeTo($address, $data)
    {
        $send = stream_socket_sendto($this->socket, $data, null, $address);

        if ($send != strlen($data)) {
            $this->blocked = true;
        }

        return $send;
    }
}
