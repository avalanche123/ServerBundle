<?php

namespace Bundle\ServerBundle;

use Symfony\Components\DependencyInjection\ContainerInterface;

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
  protected $isChild;
  protected $pid;
  protected $pidFile;

  /**
   * @param ContainerInterface $container
   */
  public function __construct(ContainerInterface $container)
  {
    if (substr(PHP_OS, 0, 3) === 'WIN')
    {
      throw new \Exception('Cannot run on windows');
    }

    if (substr(PHP_SAPI, 0, 3) !== 'cli')
    {
      throw new \Exception('Can only run on CLI environment');
    }

    if (!function_exists('pcntl_fork'))
    {
      throw new \Exception('pcntl_* functions are required');
    }

    if (!function_exists('posix_kill'))
    {
      throw new \Exception('posix_* functions are required');
    }

    $this->container = $container;
    $this->isChild   = false;
    $this->pidFile   = $this->container->getParameter('daemon.pid_file');

    $uid = $this->container->getParameter('daemon.user');
    $gid = $this->container->getParameter('daemon.group');

    if (null !== $uid && !is_int($uid))
    {
      $user = posix_getpwnam($uid);

      if (false === $user)
      {
        throw new \Exception(sprintf('User "%s" does not exist', $uid));
      }

      $uid  = $user['uid'];
    }

    if (null !== $gid && !is_int($gid))
    {
      $group = posix_getgrnam($gid);

      if (false === $group)
      {
        throw new \Exception(sprintf('Group "%s" does not exist', $gid));
      }

      $gid   = $group['gid'];
    }

    $this->uid = $uid;
    $this->gid = $gid;
  }

  /**
   * @return boolean
   */
  public function start($restart = false)
  {
    if ($restart)
    {
      $this->removePidFile();
    }

    if ($pid = $this->readPidFile())
    {
      throw new \Exception(sprintf('Daemon already started (PID: "%d")', $pid));
    }

    set_time_limit(0);

    // @see http://www.php.net/manual/en/function.pcntl-fork.php#41150
    @ob_end_flush();

    pcntl_signal(SIGCHLD, SIG_IGN);

    $pid = @pcntl_fork();

    if ($pid === -1)
    {
      throw new \Exception('Forking process failed');
    }

    if ($pid === 0)
    {
      $this->isChild = true;

      sleep(1);

      if (null !== $this->gid)
      {
         posix_setgid($this->gid);
      }

      if (null !== $this->uid)
      {
        posix_setuid($this->uid);
      }

      $this->writePidFile();

      try
      {
        $server = $this->container->getServerService();

        $server->start();
      }
      catch (\Exception $e)
      {
        echo "EXCEPTION: ".$e->getMessage().PHP_EOL;
      }

      $this->removePidFile();

      exit(0);
    }

    return true;
  }

  /**
   * @return boolean
   */
  public function restart()
  {
    return $this->start(true);
  }

  /**
   * @return boolean
   */
  public function stop()
  {
    $success = false;

    if ($pid = $this->readPidFile())
    {
      $status = 0;

      posix_kill($pid, SIGKILL);
      pcntl_waitpid($pid, $status, WNOHANG);
      $success = pcntl_wifexited($status);

      $this->removePidFile();
    }

    return $success;
  }

  /**
   * @return boolean
   */
  public function isChild()
  {
    return $this->isChild;
  }

  /**
   * @return string
   */
  protected function getPidFile()
  {
    return $this->pidFile;
  }

  /**
   * @return null|integer
   */
  protected function readPidFile()
  {
    if (null === $this->pidFile || !is_readable($this->pidFile))
    {
      return null;
    }

    return file_get_contents($this->pidFile);
  }

  /**
   * @return boolean
   */
  protected function writePidFile()
  {
    if (null === $this->pidFile)
    {
      return true;
    }

    if (is_readable($this->pidFile))
    {
      throw new \Exception(sprintf('PID file "%s" already exist', $this->pidFile));
    }

    return file_put_contents($this->pidFile, getmypid()) > 0;
  }

  /**
   * @return boolean
   */
  protected function removePidFile()
  {
    if (!is_writeable($this->pidFile))
    {
      return false;
    }

    return unlink($this->pidFile);
  }
}
