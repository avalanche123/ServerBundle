<?php

namespace Bundle\ServerBundle\Command;

use Symfony\Components\Console\Input\InputArgument,
    Symfony\Components\Console\Input\InputOption,
    Symfony\Components\Console\Input\InputInterface,
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
class StartCommand extends DaemonCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        /**
         * @TODO implement mongrel_rails like options
         * @link http://github.com/fauna/mongrel/blob/master/bin/mongrel_rails#L22
         */
        $this
          ->setDefinition(array(
                new InputOption('daemonize', '-d', InputOption::PARAMETER_NONE, 'Run daemonized in the background.'),
                new InputOption('environment', '-e', InputOption::PARAMETER_OPTIONAL, 'Symfony environment to run as.', 'production'),
                new InputOption('address', '-a', InputOption::PARAMETER_OPTIONAL, 'Address to bind to.', '*'),
                new InputOption('port', '-p', InputOption::PARAMETER_OPTIONAL, 'Which port to bind to.', 1962),
            ))
            ->setName('server:start')
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * @TODO implement mongrel_rails like runtime configuration
         * @link http://github.com/fauna/mongrel/blob/master/bin/mongrel_rails#L22
         *
         * Sequence
         *   1) runtime configuration [-defaults]
         *   2) config.yml
         *   3) server.xml
         */

        // get Server service
        $server = $this->container->getServerService();

        // start Server
        if (!$input->getOption('daemonize')) {
            if ($input->getOption('verbose')) {
                $server->setConsole($this->console);
            }

            return $server->start();
        }

        // store Daemon service
        $server->setDaemon($this->daemon);

        // start Daemon
        $this->daemon->start();
    }
}
