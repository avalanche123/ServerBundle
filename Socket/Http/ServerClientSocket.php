<?php

namespace Bundle\ServerBundle\Socket\Http;

use Bundle\ServerBundle\Socket\Http\ClientSocket;

class ServerClientSocket extends ClientSocket
{
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
