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
class StopCommand extends DaemonCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this->setName('server:stop');
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $pidFile = $this->container->getParameter('server.pid_file');

    // pid file exists
    if (file_exists($pidFile) && is_file($pidFile))
    {
      // get pid
      $pid = file_get_contents($pidFile);

      $output->writeln('server stopped');

      // send SIGTERM signal
      posix_kill($pid, SIGTERM);
    }
  }
}
