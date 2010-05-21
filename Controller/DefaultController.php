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
class DefaultController extends Controller
{
  /**
   * @return Response
   */
  public function infoAction()
  {
    $server = $this->container->getServerService();

    return $this->render('ServerBundle:Default:info', array('server' => $server));
  }

  /**
   * @return Response
   */
  public function statusAction()
  {
    $server = $this->container->getServerService();

    return $this->render('ServerBundle:Default:status', array('server' => $server));
  }
}
