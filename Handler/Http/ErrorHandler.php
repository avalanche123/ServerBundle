<?php

namespace Bundle\ServerBundle\Handler\Http;

use Symfony\Components\EventDispatcher\EventDispatcher,
    Symfony\Components\EventDispatcher\Event,
    Bundle\ServerBundle\Handler\HttpHandler;

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
 * @subpackage Handler
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class ErrorHandler extends HttpHandler
{
    /**
     * @param EventDispatcher $dispatcher
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('server.request', array($this, 'handle'));
    }

    /**
     * @param Event $event
     */
    public function handle(Event $event)
    {
        // ExceptionController-like view renderer would be cool

        // determine error source, set headers (e.g. 404, 500) etc ...

        // @TODO must be clarified how the dispatching of the event works
        // return $event / $error / whatever
    }
}
