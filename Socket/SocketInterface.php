<?php

namespace Bundle\ServerBundle\Socket;

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
 * @subpackage Socket
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
interface SocketInterface
{
    /**
     * @return resource
     */
    function getSocket();

    /**
     * @return integer
     */
    function getId();

    /**
     * @return string
     */
    function getError();

    /**
     * @return boolean
     */
    function disconnect();

    /**
     * @return boolean
     */
    function isConnected();

    /**
     * @return boolean
     */
    function isWaiting();

    /**
     * @param integer $length
     * @return string
     */
    function read($length);

    /**
     * @param string $data
     * @return integer
     */
    function write($data);

    /**
     * @return array
     */
    function getOption($option, $level = SOL_SOCKET);

    /**
     * @param string $option
     * @param mixed $value
     * @param integer $level (optional)
     * @return boolean
     */
    function setOption($option, $value, $level = SOL_SOCKET);
}
