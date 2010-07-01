<?php

namespace Bundle\ServerBundle;

use Symfony\Components\Console\Output\OutputInterface;

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
 * @subpackage Console
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class Console
{
    protected $output;

    /**
     * @param Symfony\Components\Console\Output\OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param string $message
     * @param array $parameters (optional)
     */
    public function log($message, array $parameters = array())
    {
        if (null !== $this->output) {
            $this->output->writeln(call_user_func_array('sprintf', array_merge(
                array('[%s] '.$message), array_merge( array(date('H:i:s')), $parameters)
            )));
        }
    }

    /**
     * @param string $message
     * @param array $parameters (optional)
     */
    public function info($message, array $parameters = array())
    {
        $this->log('<info>INFO</info>     '.$message, $parameters);
    }

    /**
     * @param string $message
     * @param array $parameters (optional)
     */
    public function error($message, array $parameters = array())
    {
        $this->log('<error>ERROR</error>    '.$message, $parameters);
    }

    /**
     * @param string $message
     * @param array $parameters (optional)
     */
    public function status($message, array $parameters = array())
    {
        $this->log('<info>STATUS</info>   '.$message, $parameters);
    }

    /**
     * @param string $message
     * @param array $parameters (optional)
     */
    public function request($message, array $parameters = array())
    {
        $this->log('<comment>REQUEST</comment>  '.$message, $parameters);
    }

    /**
     * @param string $message
     * @param array $parameters (optional)
     */
    public function response($message, array $parameters = array())
    {
        $this->log('<comment>RESPONSE</comment> '.$message, $parameters);
    }
}
