<?php

namespace Bundle\ServerBundle\Command;

use Symfony\Components\Console\Input\InputInterface,
    Symfony\Components\Console\Output\OutputInterface,
    Bundle\ServerBundle\Command\Command,
    Bundle\ServerBundle\Console;

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
abstract class DaemonCommand extends Command
{
    protected $daemon;

    /**
     * @see Command
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->daemon = $this->container->getDaemonService();

        if ($input->getOption('verbose')) {
            $this->console = new Console($output);
            $this->daemon->setConsole($this->console);
        }
    }
}
