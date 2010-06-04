<?php

namespace Bundle\ServerBundle;

use Bundle\ServerBundle\ServerInterface,
    Bundle\ServerBundle\EventDispatcher,
    Bundle\ServerBundle\DaemonInterface,
    Symfony\Components\Console\Output\OutputInterface,
    Bundle\ServerBundle\Request,
    Bundle\ServerBundle\Response,
    Symfony\Components\EventDispatcher\Event,
    Symfony\Foundation\Kernel,
    Bundle\ServerBundle\Bundle;

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
    protected $console;
    protected $daemon;
    protected $dispatcher;
    protected $options;
    protected $clients;
    protected $server;
    protected $shutdown;
    protected $startTime;

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
        $this->console    = null;
        $this->daemon     = null;
        $this->dispatcher = $dispatcher;
        $this->clients    = array();
        $this->server     = null;
        $this->shutdown   = false;

        $clientClass = 'Bundle\\ServerBundle\\Socket\\ClientSocket';
        $serverClass = 'Bundle\\ServerBundle\\Socket\\ServerSocket';

        // @see Resources/config/server.xml
        $this->options = array(
            'environment'            => 'dev',
            'debug'                  => true,
            'kernel_environment'     => 'prod',
            'kernel_debug'           => false,
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
     * @return Console
     */
    public function getConsole()
    {
        return $this->console;
    }

    /**
     * @param Console $console
     */
    public function setConsole(Console $console)
    {
        $this->console = $console;
    }

    /**
     * @param string $type
     * @param string $message
     * @param array $parameters (optional)
     */
    protected function logConsole($type, $message, array $parameters = array())
    {
        if (null !== $this->console && is_callable(array($this->console, $type))) {
            call_user_func(array($this->console, $type), $message, $parameters);
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
     *
     * @throws \RuntimeException If Request was not handled
     */
    public function start()
    {
        // Symfony & ServerBundle informations
        $this->logConsole('info', 'Symfony <comment>%s</comment> (<comment>%s</comment>, <comment>%s</comment>), ServerBundle <comment>%s</comment> (<comment>%s</comment>, <comment>%s</comment>)', array(
            Kernel::VERSION, $this->options['environment'],
            true === $this->options['debug'] ? 'debug' : 'non-debug',
            Bundle::VERSION, $this->options['kernel_environment'],
            true === $this->options['kernel_debug'] ? 'debug' : 'non-debug'
        ));

        // PHP informations
        $this->logConsole('info', 'PHP/<comment>%s</comment> [%s] <comment>%s</comment> pecl_http', array(
            phpversion(), PHP_SAPI, true === extension_loaded('pecl_http') ? 'with' : 'without'
        ));

        // start options
        $this->logConsole('info', 'Server#start(): pid=<comment>%d</comment>, address=<comment>%s</comment>, port=<comment>%d</comment>', array(
            getmypid(), $this->options['address'], $this->options['port']
        ));

        // stop server notice
        $this->logConsole('info', 'To stop the server, type <comment>^C</comment>');

        // timers
        $start  = time();
        $timer  = time();
        $status = time();

        // create server socket
        $this->createServerSocket();

        // @TODO spawn max_clients?

        // create select sets
        $read   = $this->createReadSet();
        $write  = null;
        $except = $this->createExceptSet();

        // max requests
        $requests = 0;

        // send bytes
        $sendTotal = 0;

        // disable max_requests_per_child in non-daemon mode
        if (null === $this->daemon || !$this->daemon->isChild()) {
            $this->options['max_requests_per_child'] = 0;
        }

        while (
            !$this->shutdown &&                                             // daemon stop?
            !$this->reachedMaxRequestsPerChild($requests) &&                // max requests?
            false !== ($events = @socket_select($read, $write, $except, 1)) // socket alive?
        ) {
            // sockets changed
            if ($events > 0) {
                // process read set
                foreach ($read as $socket) {
                    // accept client connection
                    if ($this->isServerSocket($socket)) {
                        $this->createClientSocket();
                        continue;
                    }

                    // read client data
                    if ($this->isClientSocket($socket)) {
                        $client = $this->findSocket($socket);

                        /** @var $request Request */
                        $request = $client->readResponse();

                        // Request read?
                        if (!$request instanceof Request) {
                            $client->disconnect();
                            continue;
                        }

                        // Request informations
                        $this->logConsole('request', '%s <comment>%s</comment>', array(
                            $request->getRequestMethod(),
                            $request->getRequestUrl()
                        ));

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
                        $send       = $client->sendResponse($response);
                        $sendTotal += $send;

                        // Response status
                        $message = $response->isSuccessful()
                                 ? '<info>%d %s</info> (<comment>%d</comment> bytes)'
                                 : '<error>%d %s</error> (<comment>%d</comment> bytes)';

                        // Response informations
                        $this->logConsole('response', $message, array(
                            $response->getStatusCode(),
                            $response->getStatusText(),
                            $send
                        ));

                        // @TODO is that correct, here?
                        $requests++;
                    }
                }

                // process except set
                foreach($except as $socket) {
                    // close client connection
                    if ($this->isClientSocket($socket)) {
                        $this->findSocket($socket)->disconnect();
                    }
                }
            }

            // only once a second
            if (time() - $timer >= 1) {
                foreach ($this->clients as $client) {
                    $client->timer();
                }

                $timer = time();
            }

            $this->cleanSockets();

            // only once a minute
            if (time() - $status >= 60) {
                $this->logConsole('status', 'Server#status(): requests=<comment>%d</comment>, send=<comment>%.0f</comment>kb, memory=<comment>%.0f</comment>kb, peak=<comment>%.0f</comment>kb, uptime=<comment>%s</comment>s', array(
                    $requests,
                    $sendTotal / 1024,
                    memory_get_usage(true) / 1024,
                    memory_get_peak_usage(true) / 1024,
                    time() - $start
                ));

                $status = time();
            }

            // override select sets
            $read   = $this->createReadSet();
            $write  = null;
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

        $this->logConsole('info', 'Server#stop(): okay');

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
     * @return Bundle\ServerBundle\Socket\SocketInterface
     */
    protected function createClientSocket()
    {
        $class  = $this->options['socket_client_class'];
        $client = new $class(
            $this->server->accept(),
            $this->options['timeout'],
            $this->options['keepalive_timeout']
        );

        // store socket
        $this->clients[$client->getId()] = $client;

        return $client;
    }

    /**
     * @return Bundle\ServerBundle\Socket\SocketInterface
     */
    protected function createServerSocket()
    {
        if (null !== $this->server) {
            throw new \RuntimeException('Server socket already created');
        }

        $class  = $this->options['socket_server_class'];
        $server = new $class(
            $this->options['address'],
            $this->options['port'],
            $this->options['max_clients']
        );

        return $this->server = $server;
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

        if ($this->server->getSocket() === $socket) {
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
            if (!$client->isConnected() || !is_resource($client->getSocket())) {
                unset($this->clients[$id]);
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

        if ($this->server->getSocket() === $socket) {
            return $this->server;
        }

        $id = (integer) $socket;

        if (isset($this->clients[$id])) {
            return $this->clients[$id];
        }

        return null;
    }

    /**
     * @return array
     */
    protected function createReadSet()
    {
        $set = array($this->server->getSocket());

        foreach ($this->clients as $client) {
            $set[] = $client->getSocket();
        }

        return $set;
    }

    /**
     * @return array
     */
    protected function createExceptSet()
    {
        $set = array($this->server->getSocket());

        foreach ($this->clients as $client) {
            $set[] = $client->getSocket();
        }

        return $set;
    }
}
