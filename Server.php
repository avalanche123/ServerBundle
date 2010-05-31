<?php

namespace Bundle\ServerBundle;

use Bundle\ServerBundle\ServerInterface,
    Bundle\ServerBundle\EventDispatcher,
    Bundle\ServerBundle\DaemonInterface,
    Bundle\ServerBundle\Request,
    Bundle\ServerBundle\Response,
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
class Server implements ServerInterface
{
    protected $daemon;
    protected $dispatcher;
    protected $options;
    protected $clients;
    protected $servers;
    protected $shutdown;

    /**
     * @param EventDispatcher $dispatcher
     * @param array $options (optional)
     *
     * @throws \InvalidArgumentException When an unsupported option is provided
     * @throws \InvalidArgumentException If an invalid socket client class is provided
     * @throws \InvalidArgumentException If an invalid socket server class is provided
     * @throws \InvalidArgumentException If an invalid socket server client class is provided
     */
    public function __construct(EventDispatcher $dispatcher, array $options = array())
    {
        $this->dispatcher = $dispatcher;
        $this->clients    = array();
        $this->servers    = array();
        $this->shutdown   = false;

        $clientClass       = 'Bundle\\ServerBundle\\Socket\\ClientSocket';
        $serverClass       = 'Bundle\\ServerBundle\\Socket\\ServerSocket';

        // @see Resources/config/server.xml
        $this->options = array(
            'address'                => '*',
            'port'                   => 1962,
            'max_clients'            => 100,
            'max_requests_per_child' => 1000,
            'document_root'          => null,
            'socket_client_class'    => $clientClass,
            'socket_server_class'    => $serverClass,
            'timeout'                => 90,
            'keepalive_timeout'      => 15
        );

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new \InvalidArgumentException(sprintf('The Server does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);

        // check socket client class
        if (!$this->checkSocketClass($clientClass, $this->options['socket_client_class'])) {
            throw new \InvalidArgumentException(sprintf('Client socket class must be a sublass of "%s"', $clientClass));
        }

        // check socket server class
        if (!$this->checkSocketClass($serverClass, $this->options['socket_server_class'])) {
            throw new \InvalidArgumentException(sprintf('Server socket class must be a sublass of "%s"', $serverClass));
        }
    }

    /**
     * @param DaemonInterface $daemon
     */
    public function setDaemon(DaemonInterface $daemon)
    {
        $this->daemon = $daemon;
    }

    /**
     * @return DaemonInterface
     */
    public function getDaemon()
    {
        return $this->daemon;
    }

    /**
     * @param string $expected
     * @param string $provided
     * @return boolean
     */
    protected function checkSocketClass($expected, $provided)
    {
        $r = new \ReflectionClass($provided);

        return $r->getName() == $expected || $r->isSubclassOf($expected);
    }

    /**
     * @return boolean
     *
     * @throws \RuntimeException If Request was not handled
     */
    public function start()
    {
        $timer = time();

        // create server socket
        $this->createServerSocket();

        // @TODO spawn max_clients?

        // create select sets
        $read   = $this->createReadSet();
        $write  = $this->createWriteSet();
        $except = $this->createExceptSet();

        // max requests
        $requests = 0;

        // disable max_requests_per_child in non-daemon mode
        if (null === $this->getDaemon() || !$this->getDaemon()->isChild()) {
            $this->options['max_requests_per_child'] = 0;
        }

        while (
            !$this->shutdown &&                                             // daemon stop?
            !$this->reachedMaxRequestsPerChild($requests) &&                // max requests?
            false !== ($events = @stream_select($read, $write, $except, 0)) // socket alive?
        ) {
            // sockets changed
            if ($events > 0) {
                // process read set
                foreach ($read as $socket) {
                    // accept client connection
                    if ($this->isServerSocket($socket)) {
                        $server = $this->findSocket($socket);
                        $client = $this->createClientSocket($server->accept());

                        // @TODO max clients check
                    }

                    // read client data
                    if ($this->isClientSocket($socket)) {
                        $client = $this->findSocket($socket);

                        /** @var $request Request */
                        $request = $client->readRequest();

                        // Request read?
                        if (!$request instanceof Request) {
                            // @TODO disconnect client?
                            continue;
                        }

                        /** @var $event Event */
                        $event = $this->dispatcher->notifyUntil(
                            new Event($request, 'server.request')
                        );

                        // Request handled?
                        if (!$event->isProcessed()) {
                            throw new \RuntimeException('Request is not handled');
                        }

                        /** @var $response Response */
                        $response = $event->getReturnValue();

                        /** @var $event Event */
                        $event = $this->dispatcher->filter(
                            new Event($request, 'server.response'),
                            $response
                        );

                        /** @var $response Response */
                        $response = $event->getReturnValue();

                        // @TODO add response checks?

                        // send Response
                        $client->sendResponse($response);

                        // @TODO is that correct, here?
                        $requests++;
                    }
                }

                // process write set
                foreach ($write as $socket) {
                    // send client data
                    if ($this->isClientSocket($socket)) {
                        $client = $this->findSocket($socket);

                        if (!$client->isConnected()) {
                            $client->connect();
                        }

                        // send response
                        $client->sendResponse();

                        // @TODO is that correct, here?
                        $requests++;
                    }
                }

                // process except set
                foreach($except as $socket) {
                    // close client connection
                    if ($this->isClientSocket($socket)) {
                        $client = $this->findSocket($socket);
                        $id     = $client->getId();

                        $client->disconnect();

                        if (isset($this->clients[$id])) {
                            unset($this->clients[$id]);
                        }
                    }
                }
            }

            // only once a second
            if (time() - $timer > 1) {
                foreach ($this->clients as $client) {
                    $client->timer();
                }

                $timer = time();
            }

            $this->cleanSockets();

            // override select sets
            $read   = $this->createReadSet();
            $write  = $this->createWriteSet();
            $except = $this->createExceptSet();
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function stop()
    {
        foreach ($this->clients as $client) {
            $client->disconnect();
        }

        foreach ($this->servers as $server) {
            $server->disconnect();
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function shutdown()
    {
        $this->shutdown = true;
    }

    /**
     * @param integer $requests
     * @return boolean
     */
    protected function reachedMaxRequestsPerChild($requests)
    {
        if (!$this->options['max_requests_per_child']) {
            return false;
        }

        return $requests >= $this->options['max_requests_per_child'];
    }

    /**
     * @param resource $socket
     *
     * @return Bundle\ServerBundle\Socket\SocketInterface
     */
    protected function createClientSocket($socket)
    {
        if (!is_resource($socket)) {
            throw new \InvalidArgumentException('Socket must be a valid resource');
        }

        $class  = $this->options['socket_client_class'];
        $client = new $class($socket, $this->options['timeout'], $this->options['keepalive_timeout']);

        // store socket
        $this->clients[$client->getId()] = $client;

        return $client;
    }

    /**
     * @return Bundle\ServerBundle\Socket\SocketInterface
     */
    protected function createServerSocket()
    {
        $class  = $this->options['socket_server_class'];
        $server = new $class($this->options['address'], $this->options['port']);
        $server->connect();

        // store socket
        $this->servers[$server->getId()] = $server;

        return $server;
    }

    /**
     * @param resource $socket
     * @return boolean
     *
     * @throws \InvalidArgumentException If socket is not a valid resource
     */
    protected function isClientSocket($socket)
    {
        if (!is_resource($socket)) {
            throw new \InvalidArgumentException('Socket must be a valid resource');
        }

        if (isset($this->clients[(integer) $socket])) {
            return true;
        }

        return false;
    }

    /**
     * @param resource $socket
     * @return boolean
     *
     * @throws \InvalidArgumentException If socket is not a valid resource
     */
    protected function isServerSocket($socket)
    {
        if (!is_resource($socket)) {
            throw new \InvalidArgumentException('Socket must be a valid resource');
        }

        if (isset($this->servers[(integer) $socket])) {
            return true;
        }

        return false;
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
     * @return null|SocketInterface
     *
     * @throws \InvalidArgumentException If socket is not a valid resource
     */
    protected function findSocket($socket)
    {
        if (!is_resource($socket)) {
            throw new \InvalidArgumentException('Socket must be a valid resource');
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
