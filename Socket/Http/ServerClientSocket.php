<?php

namespace Bundle\ServerBundle\Socket\Http;

use Bundle\ServerBundle\Socket\Http\ClientSocket;

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
class ServerClientSocket extends ClientSocket
{
    /**
     * @param resource $socket
     */
    public function __construct($socket)
    {
        if (!is_resource($socket)) {
            throw new \Exception('Socket must be a valid resource');
        }

        $this->socket    = $socket;
        $this->connected = true;
        $this->setBlocking(false);
        $this->setTimeout(0);
    }
}
