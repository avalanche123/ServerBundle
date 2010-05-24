<?php

namespace Bundle\ServerBundle\Socket;

use Bundle\ServerBundle\Socket\Socket;

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
abstract class HttpSocket extends Socket
{
    protected $protocol;
    protected $address;
    protected $port;

    /**
     * @param string $protocol
     * @param string $address
     * @param integer $port
     */
    public function __construct($protocol, $address, $port)
    {
        parent::__construct();

        if ('*' == $address) {
            $address = '0.0.0.0';
        }

        $this->protocol = $protocol;
        $this->address  = $address;
        $this->port     = $port;

        $this->realAddress = sprintf('%s://%s:%d',
            $this->protocol,
            $this->address,
            $this->port
        );

        // $this->connect();
    }

    /**
     * @return boolean
     */
    abstract public function connect();

    /**
     * @return string
     */
    public function getProtocol()
    {
        return $this->protocol;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @return integer
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getRealAddress()
    {
        return $this->realAddress;
    }

}