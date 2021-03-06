<?php

namespace Bundle\ServerBundle\Filter;

use Symfony\Components\EventDispatcher\EventDispatcher,
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
 * @subpackage Filter
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
interface FilterInterface
{
    /**
     * @param Symfony\Components\EventDispatcher\EventDispatcher $dispatcher
     */
    function register(EventDispatcher $dispatcher);
}
