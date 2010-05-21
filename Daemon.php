<?php

namespace Bundle\ServerBundle;

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
 * @subpackage Daemon
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class Daemon
{
  protected $daemon;

  /**
   */
  public function __construct()
  {
    $this->daemon = false;
  }

  /**
   * return @boolean
   */
  public function process()
  {
    // fork process
    $pid = pcntl_fork();

    if ($pid)
    {
      return false;
    }

    $this->daemon = true;

    return true;
  }

  /**
   * @return boolean
   */
  public function isDaemon()
  {
    return $this->daemon;
  }
}
