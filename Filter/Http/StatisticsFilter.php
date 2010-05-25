<?php

namespace Bundle\ServerBundle\Filter\Http;

use Symfony\Components\EventDispatcher\EventDispatcher,
    Symfony\Components\EventDispatcher\Event,
    Bundle\ServerBundle\Filter\HttpFilter;

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
 * @subpackage Filter
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */

class StatisticsFilter extends HttpFilter
{
    /**
     * @param EventDispatcher $dispatcher
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('server.response', array($this, 'filter'));
    }

    /**
     * @param Event $event
     * @param mixed $value
     * @return mixed
     *
     * @see EventDispatcher::filter()
     */
    public function filter(Event $event, $value)
    {
        if (!$value instanceof \HttpMessage)
        {
            return $value;
        }

        return $value;

        // collect statistics about the response, because it will be send ...
        // ... after that filter immediately. Dunno where to store that stats!

        // @TODO must be clarified how the dispatching of the event works
        // return $event / $data / whatever
    }
}
