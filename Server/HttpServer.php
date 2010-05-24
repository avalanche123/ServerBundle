<?php

namespace Bundle\ServerBundle\Server;

use Bundle\ServerBundle\Server\Server,
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
    protected $shutdown;

    /**
     * @param EventDispatcher $dispatcher
     * @param array $options (optional)
     *
     * @throws \Exception If pecl_http extension is not loaded
     * @throws \InvalidArgumentException When unsupported option is provided
     * @throws \InvalidArgumentException If invalid socket client class is provided
     * @throws \InvalidArgumentException If invalid socket server class is provided
     * @throws \InvalidArgumentException If invalid socket server client class is provided
     */
    public function __construct(EventDispatcher $dispatcher, array $options = array())
    {
        if (!extension_loaded('http')) {
            throw new \Exception('pecl_http extension not loaded.');
        }

        $this->dispatcher = $dispatcher;
        $this->clients    = array();
        $this->servers    = array();
        $this->shutdown   = false;

        $clientClass       = 'Bundle\\ServerBundle\\Socket\\Http\\ClientSocket';
        $serverClass       = 'Bundle\\ServerBundle\\Socket\\Http\\ServerSocket';
        $serverClientClass = 'Bundle\\ServerBundle\\Socket\\Http\\ServerClientSocket';

        $this->options = array(
            'protocol'                   => 'tcp',
            'address'                    => '*',
            'port'                       => 1962,
            'max_clients'                => 100,
            'max_requests_per_child'     => 1000,
            'document_root'              => null,
            'socket_client_class'        => $clientClass,
            'socket_server_class'        => $serverClass,
            'socket_server_client_class' => $serverClientClass
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

        // check socket server client class
        if (!$this->checkSocketClass($serverClientClass, $this->options['socket_server_client_class'])) {
            throw new \InvalidArgumentException(sprintf('Server client socket class must be a sublass of "%s"', $serverClientClass));
        }
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

        while (
            !$this->shutdown &&                                             // daemon stop?
            $requests < $this->options['max_requests_per_child'] &&         // max requests?
            false !== ($events = @stream_select($read, $write, $except, 0)) // socket alive?
        ) {
            if ($events > 0) {
                foreach ($read as $socket) {
                    if ($this->isServerSocket($socket)) {
                        $server = $this->findSocket($socket);
                        $client = $server->accept();

                        // store client socket
                        $this->clients[(integer) $client->getSocket()] = $client;

                        // @TODO max clients check
                    }

                    if ($this->isClientSocket($socket)) {
                        $client  = $this->findSocket($socket);

                        // @TODO see the following lines

                        // something like this to notify the handlers, until ...
                        // ... one handler returns a HttpMessage? Where do I ...
                        // ... store the HttpMessage and how do I get into the ...
                        // ... next round of this loop to be written? I think ...
                        // ... I need write buffers in clients? :)

                        /**
                         * $message = new HttpMessage($data);
                         * $version = $message->getHttpVersion();
                         * $url     = $message->getRequestUrl();
                         * $method  = $message->getRequestMethod();
                         * $headers = $message->getHeaders();
                         * $body    = $message->getBody();
                         */

                        // $message = $client->doRead(); // not ->read()!

                        /* $this->dispatcher->notifyUntil(new Event($message, 'server.request', array(
                            'socket' => $client
                        ))); */

                        // @TODO is that correct, here?
                        $requests++;
                    }
                }

                foreach ($write as $socket) {
                    if ($this->isClientSocket($socket)) {
                        $client = $this->findSocket($socket);

                        if (!$client->isConnected()) {
                            $client->connect();
                        }

                        // @TODO see the following lines

                        // something like this to filter the generated ...
                        // ... HttpMessage which was generated and bufferd ...
                        // ... by the previous $read round of this loop. I ...
                        // ... think I really need write buffers in clients? ;O

                        /**
                         * $message = new HttpMessage();
                         * $message->setType(HTTP_MSG_RESPONSE);
                         * $message->setHttpVersion($version);
                         * $message->setResponseCode($statusCode);
                         * $message->setResponseStatus($statusMessage);
                         * $message->setHeaders($headers);
                         * $message->setBody($body);
                         */

                        /* $message = $this->dispatcher->filter(new Event($data, 'server.response', array(
                            'socket' => $client
                        ))) */

                        // $client->doWrite($message); // not ->write()!

                        // @TODO is that correct, here?
                        $requests++;
                    }
                }

                foreach($except as $socket) {
                    if ($this->isClientSocket($socket)) {
                        $client = $this->findSocket($socket);
                        $id     = (integer) $client->getSocket();

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

            $rem = $this->cleanSockets();

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
     * @return Bundle\ServerBundle\Socket\SocketInterface
     */
    protected function createClientSocket()
    {
        $class  = $this->options['socket_client_class'];
        $client = new $class($this->options['protocol'], $this->options['address'], $this->options['port']);
        $client->connect();

        // store socket
        $this->clients[(integer) $client->getSocket()] = $client;

        return $client;
    }

    /**
     * @return Bundle\ServerBundle\Socket\SocketInterface
     */
    protected function createServerSocket()
    {
        $class  = $this->options['socket_server_class'];
        $server = new $class($this->options['socket_server_client_class'], $this->options['protocol'], $this->options['address'], $this->options['port']);
        $server->connect();

        // store socket
        $this->servers[(integer) $server->getSocket()] = $server;

        return $server;
    }

    protected function isClientSocket($socket)
    {
        if (!is_resource($socket)) {
            throw new \Exception('Socket must be a valid resource');
        }

        if (isset($this->clients[(integer) $socket])) {
            return true;
        }

        return false;
    }

    protected function isServerSocket($socket)
    {
        if (!is_resource($socket)) {
            throw new \Exception('Socket must be a valid resource');
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
