<?php

namespace Bundle\ServerBundle\Controller;

use Symfony\Framework\WebBundle\Controller;

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
 * @subpackage Controller
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
abstract class ServerController extends Controller
{
    /**
     * @return Bundle\ServerBundle\DaemonInterface
     */
    public function getDaemon()
    {
        return $this->container->getDaemonService();
    }

    /**
     * @return Bundle\ServerBundle\ServerInterface
     */
    public function getServer()
    {
        return $this->container->getServerService();
    }
}
