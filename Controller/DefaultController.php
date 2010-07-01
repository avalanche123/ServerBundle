<?php

namespace Bundle\ServerBundle\Controller;

use Bundle\ServerBundle\Controller\ServerController;

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
class DefaultController extends ServerController
{
    /**
     * @return Symfony\Components\HttpKernel\Response
     */
    public function infoAction()
    {
        return $this->render('ServerBundle:Default:info', array('server' => $this->getServer()));
    }

    /**
     * @return Symfony\Components\HttpKernel\Response
     */
    public function statusAction()
    {
        return $this->render('ServerBundle:Default:status', array('server' => $this->getServer()));
    }
}
