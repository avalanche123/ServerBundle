<?php

namespace Bundle\ServerBundle;

use Bundle\ServerBundle\Console;

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
     * @return boolean
     */
    function restart();

    /**
     * @return Bundle\ServerBundle\Console
     */
    function getConsole();

    /**
     * @param Bundle\ServerBundle\Console $console
     */
    function setConsole(Console $console);
}
