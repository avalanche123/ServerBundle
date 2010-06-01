<?php

namespace Bundle\ServerBundle;

use Bundle\ServerBundle\DaemonInterface,
    Symfony\Components\Console\Output\OutputInterface;

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
     * @return OutputInterface
     */
    function getOutput();

    /**
     * @param OutputInterface $output
     */
    function setOutput(OutputInterface $output);
}
