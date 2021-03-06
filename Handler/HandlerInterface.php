<?php

namespace Bundle\ServerBundle\Handler;

use Symfony\Components\EventDispatcher\EventDispatcher;

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
 * @subpackage Handler
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
interface HandlerInterface
{
    /**
     * @param EventDispatcher $dispatcher
     */
    function register(EventDispatcher $dispatcher);
}
