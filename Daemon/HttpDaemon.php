<?php

namespace Bundle\ServerBundle\Daemon;

use Bundle\ServerBundle\Server\HttpServer,
    Bundle\ServerBundle\Daemon\Daemon;

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
 * @subpackage Daemon
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class HttpDaemon extends Daemon
{
    protected $server;

    /**
     * @param HttpServer $server
     * @param string $pidFile
     * @param integer|string $user (optional)
     * @param integer|string $group (optional)
     * @param integer $umask (optional)
     */
    public function __construct(HttpServer $server, $pidFile, $user = null, $group = null, $umask = null)
    {
        parent::__construct($pidFile, $user, $group, $umask);

        $this->server = $server;
        $this->server->setDaemon($this);

        declare(ticks = 1);

        // pcntl signal handlers
        pcntl_signal(SIGTERM, array($this, 'signalHandler'));
    }

    /**
     * @return boolean
     */
    protected function process()
    {
        return $this->server->start();
    }

    /**
     * @param integer $signo
     */
    public function signalHandler($signo)
    {
        switch ($signo) {
            case SIGTERM:
                $this->server->shutdown();
            break;
        }
    }
}
