<?php

namespace Bundle\ServerBundle;

use Symfony\Foundation\Bundle\Bundle as BaseBundle,
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
class Bundle extends BaseBundle
{
  /**
   * @param ContainerInterface $container
   */
  public function buildContainer(ContainerInterface $container)
  {
    Loader::registerExtension(new ServerExtension());
  }
}
