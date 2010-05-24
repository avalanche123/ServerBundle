<?php

namespace Bundle\ServerBundle;

use Symfony\Components\EventDispatcher\EventDispatcher as BaseEventDispatcher,
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
 * @subpackage EventDispatcher
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class EventDispatcher extends BaseEventDispatcher
{
    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        // register request handlers
        foreach ($container->findAnnotatedServiceIds('server.request') as $id => $attributes) {
            $container->getService($id)->register($this);
        }

        // register response filters
        foreach ($container->findAnnotatedServiceIds('server.response') as $id => $attributes) {
            $container->getService($id)->register($this);
        }
    }
}