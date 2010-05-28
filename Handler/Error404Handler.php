<?php

namespace Bundle\ServerBundle\Handler;

use Bundle\ServerBundle\Handler\HandlerInterface,
    Symfony\Components\EventDispatcher\EventDispatcher,
    Symfony\Components\EventDispatcher\Event;

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
class Error404Handler implements HandlerInterface
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
     *
     * @see EventDispatcher::notifyUntil()
     */
    public function handle(Event $event)
    {
        // get HttpMessage request
        $request = $event->getSubject();

        $code    = 404;
        $status  = 'Not Found';
        $headers = array();
        $content = '<h1>Error 404 - Not Found</h1>';
        // ExceptionController-like view renderer would be cool

        // add Date header
        $date = new \DateTime();
        $headers['Date'] = $date->format(DATE_RFC822);

        // add Content-Length header
        $headers['Content-Length'] = strlen($content);

        // build HttpMessage response
        $response = new \HttpMessage();
        $response->setHttpVersion($request->getHttpVersion());
        $response->setType(HTTP_MSG_RESPONSE);
        $response->setResponseCode($code);
        $response->setResponseStatus($status);
        $response->addHeaders($headers);
        $response->setBody($content);

        $event->setReturnValue($response);

        return true;
    }
}
