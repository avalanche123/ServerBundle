<?php

namespace Bundle\ServerBundle;

use Bundle\ServerBundle\ServerInterface,
    Bundle\ServerBundle\EventDispatcher,
    Bundle\ServerBundle\Socket\ServerSocket,
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
    protected $dispatcher;
    protected $isDaemon;
    protected $options;
    protected $clients;
    protected $server;
    protected $shutdown;
    protected $startTime;

    /**
     * @param EventDispatcher $dispatcher
     * @param ServerSocket $server
     * @param array $options (optional)
     *
     * @throws \InvalidArgumentException When an unsupported option is provided
     * @throws \InvalidArgumentException If provided user does not exist
     * @throws \InvalidArgumentException If provided group does not exist
     * @throws \InvalidArgumentException If an invalid socket client class is provided
     * @throws \InvalidArgumentException If an invalid socket server class is provided
     * @throws \InvalidArgumentException If an invalid socket server client class is provided
     */
    public function __construct(EventDispatcher $dispatcher, ServerSocket $server, array $options = array())
    {
        $this->dispatcher = $dispatcher;
        $this->server     = $server;
        $this->console    = null;
        $this->isDaemon   = false;
        $this->clients    = array();
        $this->shutdown   = false;

        // @see Resources/config/server.xml
        $this->options = array(
            'pid_file'               => null,
            'user'                   => null,
            'group'                  => null,
            'umask'                  => null,
            'environment'            => 'dev',
            'debug'                  => true,
            'kernel_environment'     => 'prod',
            'kernel_debug'           => false,
            'address'                => '*',
            'port'                   => 1962,
            'max_clients'            => 100,
            'max_requests_per_child' => 1000,
            'document_root'          => null,
            'timeout'                => 90,
            'keepalive_timeout'      => 15
        );

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new \InvalidArgumentException(sprintf('The Server does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);

        // convert user name to user id
        if (null !== $this->options['user'] && !is_int($this->options['user'])) {
            $user = posix_getpwnam($this->options['user']);

            if (false === $user) {
                throw new \InvalidArgumentException(sprintf('User "%s" does not exist', $this->options['user']));
            }

            $this->options['user'] = $user['uid'];
        }

        // convert group name to group id
        if (null !== $this->options['group'] && !is_int($this->options['group'])) {
            $group = posix_getgrnam($this->options['group']);

            if (false === $group) {
                throw new \InvalidArgumentException(sprintf('Group "%s" does not exist', $this->options['group']));
            }

            $this->options['group'] = $group['gid'];
        }

        if (null !== $this->options['umask']) {
            umask($this->options['umask']);
        }

        declare(ticks = 1);

        // pcntl signal handlers
        pcntl_signal(SIGHUP, array($this, 'signalHandler'));
        pcntl_signal(SIGINT, array($this, 'signalHandler'));
        pcntl_signal(SIGTERM, array($this, 'signalHandler'));
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
        if (!$this->isDaemon && null !== $this->console && is_callable(array($this->console, $type))) {
            call_user_func(array($this->console, $type), $message, $parameters);
        }
    }

    /**
     * @param integer $signo
     */
    public function signalHandler($signo)
    {
        switch ($signo) {
            case SIGHUP:
            case SIGINT:
            case SIGTERM:
                $this->shutdown();
            break;
        }
    }

    /**
     * @param boolean $daemon (optional)
     * @return boolean
     *
     * @throws \RuntimeException If you run this server on a Windows
     * @throws \RuntimeException If you run this server not in CLI environment
     * @throws \RuntimeException If pcntl_* functions are not available
     * @throws \RuntimeException If posix_* functions are not available
     * @throws \RuntimeException If the server is already started
     * @throws \RuntimeException If the process forking fails
     */
    public function start($daemon = false)
    {
        if (substr(PHP_OS, 0, 3) === 'WIN') {
            throw new \RuntimeException('Cannot run on windows');
        }

        if (substr(PHP_SAPI, 0, 3) !== 'cli') {
            throw new \RuntimeException('Can only run on CLI environment');
        }

        set_time_limit(0);

        // informations
        $this->logConsole('info', 'Symfony <comment>%s</comment> (<comment>%s</comment>, <comment>%s</comment>), ServerBundle <comment>%s</comment> (<comment>%s</comment>, <comment>%s</comment>), PHP/<comment>%s</comment> [%s]', array(
            Kernel::VERSION, $this->options['environment'],
            true === $this->options['debug'] ? 'debug' : 'non-debug',
            Bundle::VERSION, $this->options['kernel_environment'],
            true === $this->options['kernel_debug'] ? 'debug' : 'non-debug',
            phpversion(), PHP_SAPI
        ));

        // daemonize
        if (true === $daemon) {
            if (!function_exists('pcntl_fork')) {
                throw new \RuntimeException('pcntl_* functions are required');
            }

            if (!function_exists('posix_kill')) {
                throw new \RuntimeException('posix_* functions are required');
            }

            if (null !== $pid = $this->readPidFile()) {
                throw new \RuntimeException(sprintf('Server already started with pid "%d"', $pid));
            }

            // @see http://www.php.net/manual/en/function.pcntl-fork.php#41150
            @ob_end_flush();

            pcntl_signal(SIGCHLD, SIG_IGN);

            $pid = @pcntl_fork();

            if ($pid === -1) {
                throw new \RuntimeException('Forking process failed');
            }

            if ($pid === 0) {
                $this->isDaemon = true;

                sleep(1);

                if (null !== $this->options['user']) {
                    posix_setuid($this->options['user']);
                }

                if (null !== $this->options['group']) {
                    posix_setgid($this->options['group']);
                }

                $this->writePidFile();

                $this->run();

                $this->removePidFile();

                exit(0);
            }

            // start options
            $this->logConsole('info', 'Server#start(): pid=<comment>%d</comment>, address=<comment>%s</comment>, port=<comment>%d</comment>', array(
                $pid, $this->options['address'], $this->options['port']
            ));
        } else {
            // start options
            $this->logConsole('info', 'Server#start(): pid=<comment>%d</comment>, address=<comment>%s</comment>, port=<comment>%d</comment>', array(
                getmypid(), $this->options['address'], $this->options['port']
            ));

            // stop server notice
            $this->logConsole('info', 'To stop the server, type <comment>^C</comment>');

            $this->run();
        }

        return true;
    }

    /**
     * @return boolean
     *
     * @throws \RuntimeException If Request was not handled
     */
    protected function run()
    {
        // connect server socket
        $this->server->connect();

        // timers
        $start  = time();
        $timer  = time();
        $status = time();

        // create select sets
        $read   = $this->createReadSet();
        $write  = null;
        $except = $this->createExceptSet();

        // max requests
        $requests = 0;

        // send bytes
        $sendTotal = 0;

        // disable max_requests_per_child in non-daemon mode
        if (!$this->isDaemon) {
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
                        $request = $client->readRequest();

                        // Request read?
                        if (false === $request) {
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
                            new Event($request, 'server.request', array(
                                'server' => $this->server,
                                'client' => $client
                            ))
                        );

                        // Request handled?
                        if (!$event->isProcessed()) {
                            throw new \RuntimeException('Request is not handled');
                        }

                        /** @var $response Response */
                        $response = $event->getReturnValue();

                        /** @var $event Event */
                        $event = $this->dispatcher->filter(
                            new Event($request, 'server.response', array(
                                'server' => $this->server,
                                'client' => $client
                            )),
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
                                 ? '<info>%d %s</info> (<comment>%d</comment> bytes%s)'
                                 : '<error>%d %s</error> (<comment>%d</comment> bytes%s)';

                        // Compression status
                        $compression = '';
                        if ($event->hasParameter('compression') && $event->getParameter('compression')) {
                            $compression = sprintf(' [<comment>%s/%d</comment>]',
                                $event->getParameter('compression.encoding'),
                                $event->getParameter('compression.level')
                            );
                        }

                        // Response informations
                        $this->logConsole('response', $message, array(
                            $response->getStatusCode(),
                            $response->getStatusText(),
                            $send, $compression
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

        $this->stop();

        return true;
    }

    /**
     * @return boolean
     */
    public function restart()
    {
        if (true === $this->stop()) {
            return $this->start(true);
        }

        return false;
    }

    /**
     * @return boolean
     */
    public function stop()
    {
        $success = false;

        // daemon
        if ($pid = $this->readPidFile()) {
            $status = 0;

            posix_kill($pid, SIGKILL);
            pcntl_waitpid($pid, $status, WNOHANG);
            $success = pcntl_wifexited($status);

            $this->removePidFile();
        } else {
            // disconnect clients
            foreach ($this->clients as $client) {
                $client->disconnect();
            }

            // disconnect server
            $this->server->disconnect();

            $success = true;
        }

        $this->logConsole($success ? 'info' : 'error', 'Server#stop(): %s', array($success ? 'okay' : 'failed'));

        return $success;
    }

    /**
     * @return boolean
     */
    public function shutdown()
    {
        $this->shutdown = true;
    }

    /**
     * @return null|integer
     */
    protected function readPidFile()
    {
        if (null === $this->options['pid_file'] || !is_readable($this->options['pid_file'])) {
            return null;
        }

        return file_get_contents($this->options['pid_file']);
    }

    /**
     * @return boolean
     *
     * @throws \RuntimeException If pid file already exist
     */
    protected function writePidFile()
    {
        if (null === $this->options['pid_file']) {
            return true;
        }

        if (is_readable($this->options['pid_file'])) {
            throw new \RuntimeException(sprintf('Pid file "%s" already exist', $this->options['pid_file']));
        }

        return file_put_contents($this->options['pid_file'], getmypid()) > 0;
    }

    /**
     * @return boolean
     *
     * @throws \RuntimeException If pid file is not writeable
     */
    protected function removePidFile()
    {
        if (!is_writeable($this->options['pid_file'])) {
            throw new \RuntimeException(sprintf('Cannot delete pid file "%s"', $this->options['pid_file']));
        }

        return unlink($this->options['pid_file']);
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
        $client = $this->server->createClient();

        // store socket
        $this->clients[$client->getId()] = $client;

        return $client;
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
