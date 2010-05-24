<?php

namespace Bundle\ServerBundle\Server;

use Bundle\ServerBundle\Server\Server,
    Bundle\ServerBundle\Daemon\HttpDaemon,
    Bundle\ServerBundle\EventDispatcher,
    Symfony\Components\EventDispatcher\Event;

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
 * @subpackage Server
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class HttpServer extends Server
{
    protected $dispatcher;
    protected $options;
    protected $clients;
    protected $servers;

    /**
     * @param HttpDaemon $daemon
     * @param EventDispatcher $dispatcher
     * @param array $options (optional)
     *
     * @throws \InvalidArgumentException When unsupported option is provided
     */
    public function __construct(HttpDaemon $daemon, EventDispatcher $dispatcher, array $options = array())
    {
        parent::__construct($daemon);

        $this->dispatcher = $dispatcher;

        $this->options = array(
            'protocol'               => 'tcp',
            'address'                => '*',
            'port'                   => 1962,
            'max_clients'            => 100,
            'max_requests_per_child' => 1000,
            'document_root'          => null,
            'socket_client_class'    => 'Bundle\\ServerBundle\\Socket\\Http\\ClientSocket',
            'socket_server_class'    => 'Bundle\\ServerBundle\\Socket\\Http\\ServerSocket',
        );

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new \InvalidArgumentException(sprintf('The Server does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);

        $this->clients = array();
        $this->servers = array();
    }

    /**
     * @return boolean
     */
    public function start()
    {
        return true;
    }

    /**
     * @return boolean
     */
    public function stop()
    {
        return true;
    }

    /**
     * @return boolean
     */
    public function shutdown()
    {
        return true;
    }

    /**
     * @return Bundle\ServerBundle\Socket\SocketInterface
     */
    protected function createClientSocket()
    {
        $class  = $this->options['socket_client_class'];
        $client = new $class($this->options['protocol'], $this->options['address'], $this->options['port']);
        $id     = (integer) $client->getSocket();

        // store socket
        $this->clients[$id] = $client;

        return $client;
    }

    /**
     * @return Bundle\ServerBundle\Socket\SocketInterface
     */
    protected function createServerSocket()
    {
        $class  = $this->options['socket_server_class'];
        $server = new $class($this->options['protocol'], $this->options['address'], $this->options['port']);
        $id     = (integer) $server->getSocket();

        // store socket
        $this->servers[$id] = $server;

        return $server;
    }

    /**
     * @return integer
     */
    protected function cleanSockets()
    {
        $removed = 0;

        foreach ($this->clients as $id => $client) {
            if (!$client->isConnected()) {
                unset($this->clients[$id]);
                $removed++;
            }
        }

        foreach ($this->servers as $id => $server) {
            if (!$server->isConnected()) {
                unset($this->servers[$id]);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @param resource $socket
     * @return null|Bundle\ServerBundle\Socket\SocketInterface
     *
     * @throws \Exception If socket is not a valid resource
     */
    protected function findSocket($socket)
    {
        if (!is_resource($socket)) {
            throw new \Exception('Socket must be a valid resource');
        }

        $id = (integer) $socket;

        if (isset($this->clients[$id])) {
            return $this->clients[$id];
        }

        if (isset($this->servers[$id])) {
            return $this->servers[$id];
        }

        return null;
    }

    /**
     * @return array
     */
    protected function createReadSet()
    {
        $set = array();

        foreach ($this->clients as $client) {
            $set[] = $client->getSocket();
        }

        foreach ($this->servers as $server) {
            $set[] = $server->getSocket();
        }

        return $set;
    }

    /**
     * @return array
     */
    protected function createWriteSet()
    {
        $set = array();

        foreach ($this->clients as $client) {
            if ($client->isConnected() && $client->isWaiting()) {
                $set[] = $client->getSocket();
            }
        }

        foreach ($this->servers as $server) {
            if ($server->isWaiting()) {
                $set[] = $server->getSocket();
            }
        }

        return $set;
    }

    /**
     * @return array
     */
    protected function createExceptSet()
    {
        $set = array();

        foreach ($this->clients as $client) {
            $set[] = $client->getSocket();
        }

        foreach ($this->servers as $server) {
            $set[] = $server->getSocket();
        }

        return $set;
    }
}
