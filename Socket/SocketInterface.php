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
     * @param string $address
     * @param integer $port
     * @return boolean
     */
    function connect($address, $port);

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
     * @param string $address
     * @param integer $length
     * @return string
     */
    function readFrom($address, $length);

    /**
     * @param string $data
     * @return integer
     */
    function write($data);

    /**
     * @param string $address
     * @param string $data
     * @return integer
     */
    function writeTo($address, $data);

    /**
     * @return string
     */
    function getName();

    /**
     * @return string
     */
    function getPeerName();

    /**
     * @return array
     */
    function getOptions();

    /**
     * @param string $wrapper
     * @param string $option
     * @param mixed $value
     * @return boolean
     */
    function setOption($wrapper, $option, $value);
}
