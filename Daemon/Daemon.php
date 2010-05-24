<?php

namespace Bundle\ServerBundle\Daemon;

use Bundle\ServerBundle\Daemon\DaemonInterface;

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
abstract class Daemon implements DaemonInterface
{
    protected $isChild;
    protected $pidFile;
    protected $group;
    protected $user;

    /**
     * @param string $pidFile
     * @param integer|string $user (optional)
     * @param integer|string $group (optional)
     * @param integer $umask (optional)
     *
     * @throws \Exception If trying to run on Windows
     * @throws \Exception If trying to run on an environment other than CLI
     * @throws \Exception If pcntl_* functions are not available
     * @throws \Exception if posix_* functions are not available
     */
    public function __construct($pidFile, $user = null, $group = null, $umask = null)
    {
        if (substr(PHP_OS, 0, 3) === 'WIN') {
            throw new \Exception('Cannot run on windows');
        }

        if (substr(PHP_SAPI, 0, 3) !== 'cli') {
            throw new \Exception('Can only run on CLI environment');
        }

        if (!function_exists('pcntl_fork')) {
            throw new \Exception('pcntl_* functions are required');
        }

        if (!function_exists('posix_kill')) {
            throw new \Exception('posix_* functions are required');
        }

        $this->isChild = false;
        $this->pidFile = $pidFile;
        $this->user    = $user;
        $this->group   = $group;

        // convert user name to user id
        if (null !== $this->user && !is_int($this->user)) {
            $user = posix_getpwnam($this->user);

            if (false === $user) {
                throw new \Exception(sprintf('User "%s" does not exist', $this->user));
            }

            $this->user = $user['uid'];
        }

        // convert group name to group id
        if (null !== $this->group && !is_int($this->group)) {
            $group = posix_getgrnam($this->group);

            if (false === $group) {
                throw new \Exception(sprintf('Group "%s" does not exist', $this->group));
            }

            $this->group = $group['gid'];
        }

        if (null !== $umask) {
            umask($umask);
        }
    }

    /**
     * @return boolean
     *
     * @throws \Exception If a daemon is already started
     */
    public function start()
    {
        if ($pid = $this->readPidFile()) {
            throw new \Exception(sprintf('Daemon already started (PID: "%d")', $pid));
        }

        set_time_limit(0);

        // @see http://www.php.net/manual/en/function.pcntl-fork.php#41150
        @ob_end_flush();

        pcntl_signal(SIGCHLD, SIG_IGN);

        $pid = @pcntl_fork();

        if ($pid === -1) {
            throw new \Exception('Forking process failed');
        }

        if ($pid === 0) {
            $this->isChild = true;

            sleep(1);

            if (null !== $this->user) {
                posix_setuid($this->user);
            }

            if (null !== $this->group) {
                posix_setgid($this->group);
            }

            $this->writePidFile();

            try {
                $this->process();
            } catch (\Exception $e) {
            }

            $this->removePidFile();

            exit(0);
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function stop()
    {
        $success = false;

        if ($pid = $this->readPidFile()) {
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
    public function restart()
    {
        $this->removePidFile();

        return $this->start();
    }

    /**
     * @return boolean
     */
    public function isChild()
    {
        return $this->isChild;
    }

    /**
     * @return null|integer
     */
    protected function readPidFile()
    {
        if (null === $this->pidFile || !is_readable($this->pidFile)) {
            return null;
        }

        return file_get_contents($this->pidFile);
    }


    /**
     * @return boolean
     *
     * @throws \Exception If pid file already exist
     */
    protected function writePidFile()
    {
        if (null === $this->pidFile) {
            return true;
        }

        if (is_readable($this->pidFile)) {
            throw new \Exception(sprintf('Pid file "%s" already exist', $this->pidFile));
        }

        return file_put_contents($this->pidFile, getmypid()) > 0;
    }

    /**
     * @return boolean
     *
     * @throws \Exception If pid file is not writeable
     */
    protected function removePidFile()
    {
        if (!is_writeable($this->pidFile)) {
            throw new \Exception(sprintf('Cannot delete pid file "%s"', $this->pidFile));
        }

        return unlink($this->pidFile);
    }
}
