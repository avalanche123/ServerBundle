<?php

namespace Bundle\ServerBundle\Server;

use Bundle\ServerBundle\Server\ServerInterface,
    Bundle\ServerBundle\Daemon\DaemonInterface;

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
 * @subpackage Server
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
abstract class Server implements ServerInterface
{
    protected $daemon;

    /**
     * @param DaemonInterface $daemon
     */
    public function __construct(DaemonInterface $daemon)
    {
        $this->daemon = $daemon;
    }
}
