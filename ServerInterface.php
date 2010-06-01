<?php

namespace Bundle\ServerBundle;

use Bundle\ServerBundle\DaemonInterface,
    Bundle\ServerBundle\Console;

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
interface ServerInterface
{
    /**
     * @return boolean
     */
    function start();

    /**
     * @return boolean
     */
    function stop();

    /**
     */
    function shutdown();

    /**
     * @return DaemonInterface
     */
    function getDaemon();

    /**
     * @param DaemonInterface $daemon
     */
    function setDaemon(DaemonInterface $daemon);

    /**
     * @return Console
     */
    function getConsole();

    /**
     * @param Console $console
     */
    function setConsole(Console $console);
}
