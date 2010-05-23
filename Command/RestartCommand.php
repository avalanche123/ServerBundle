<?php

namespace Bundle\ServerBundle\Command;

use Symfony\Components\Console\Input\InputInterface,
    Symfony\Components\Console\Output\OutputInterface,
    Bundle\ServerBundle\Command\DaemonCommand;

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
class RestartCommand extends DaemonCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this->setName('server:restart');
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $daemon = $this->container->getDaemonService();

    if ($daemon->stop())
    {
      $output->writeln('server stopped');
    }
    else
    {
      $output->writeln('cannot stop server');
    }

    if ($daemon->start())
    {
      $output->writeln('server started');
    }
    else
    {
      $output->writeln('cannot start server');
    }
  }
}
