<?php

namespace Bundle\ServerBundle\Filter;

use Bundle\ServerBundle\Filter\FilterInterface,
    Symfony\Components\EventDispatcher\EventDispatcher,
    Symfony\Components\EventDispatcher\Event,
    Bundle\ServerBundle\ResponseInterface;

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
class StatisticsFilter implements FilterInterface
{
    /**
     * @param Symfony\Components\EventDispatcher\EventDispatcher $dispatcher
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('server.response', array($this, 'filter'));
    }

    /**
     * @param Symfony\Components\EventDispatcher\Event $event
     * @param Bundle\ServerBundle\ResponseInterface $response
     * @return Bundle\ServerBundle\ResponseInterface
     *
     * @see Symfony\Components\EventDispatcher\EventDispatcher::filter()
     */
    public function filter(Event $event, ResponseInterface $response)
    {
        return $response;

        // collect statistics about the response, because it will be send ...
        // ... after that filter immediately. Dunno where to store that stats!

        // @TODO must be clarified how the dispatching of the event works
        // return $event / $data / whatever
    }
}
