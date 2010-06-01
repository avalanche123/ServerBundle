<?php

namespace Bundle\ServerBundle;

use Symfony\Components\Console\Output\OutputInterface;

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
interface DaemonInterface
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
     * @return boolean
     */
    function restart();

    /**
     * @return boolean
     */
    function isChild();

    /**
     * @return OutputInterface
     */
    function getOutput();

    /**
     * @param OutputInterface $output
     */
    function setOutput(OutputInterface $output);
}
