<?php

namespace Bundle\ServerBundle\Command;

use Symfony\Components\Console\Input\InputInterface,
    Symfony\Components\Console\Output\OutputInterface,
    Symfony\Components\Console\Command\Command as BaseCommand;

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
 * @subpackage Command
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
abstract class Command extends BaseCommand
{
  protected $container;

  /**
   * @see Command
   */
  protected function initialize(InputInterface $input, OutputInterface $output)
  {
    $this->container = $this->application->getKernel()->getContainer();
  }
}
