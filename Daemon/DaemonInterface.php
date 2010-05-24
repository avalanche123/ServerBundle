<?php

namespace Bundle\ServerBundle\Daemon;

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
}
