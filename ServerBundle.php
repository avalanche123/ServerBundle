<?php

namespace Bundle\ServerBundle;

use Symfony\Foundation\Bundle\Bundle,
    Symfony\Components\DependencyInjection\ContainerInterface,
    Symfony\Components\DependencyInjection\Loader\Loader,
    Bundle\ServerBundle\DependencyInjection\ServerExtension;

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
 * @subpackage Bundle
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class ServerBundle extends Bundle
{
    const VERSION = '1.0.0-DEV';

    /**
     * @param Symfony\Components\DependencyInjection\ContainerInterface $container
     * @return Symfony\Components\DependencyInjection\ContainerInterface
     */
    public function buildContainer(ContainerInterface $container)
    {
        Loader::registerExtension(new ServerExtension($container));

        return $container;
    }
}
