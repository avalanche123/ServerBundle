<?php

namespace Bundle\ServerBundle\Handler;

use Bundle\ServerBundle\Handler\HandlerInterface,
    Symfony\Components\DependencyInjection\ContainerInterface;

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
abstract class Handler implements HandlerInterface
{
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
}
