<?php

namespace Bundle\ServerBundle\Handler;

use Bundle\ServerBundle\Handler\Handler,
    Bundle\ServerBundle\Response,
    Symfony\Components\EventDispatcher\EventDispatcher,
    Symfony\Components\EventDispatcher\Event,
    Symfony\Components\HttpKernel\Response as SymfonyResponse;

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
class Error404Handler extends Handler
{
    /**
     * @param Symfony\Components\EventDispatcher\EventDispatcher $dispatcher
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('server.request', array($this, 'handle'));
    }

    /**
     * @param Symfony\Components\EventDispatcher\Event $event
     *
     * @see Symfony\Components\EventDispatcher\EventDispatcher::notifyUntil()
     */
    public function handle(Event $event)
    {
        // get HttpMessage request
        $request = $event->getSubject();

        $code    = 404;
        $status  = SymfonyResponse::$statusTexts[$code];
        $headers = array();
        $content = '<h1>Error 404 - Not Found</h1>';
        // ExceptionController-like view renderer would be cool

        // add Date header
        $date = new \DateTime();
        $headers['Date'] = $date->format(DATE_RFC822);

        // add Content-Length header
        $headers['Content-Length'] = strlen($content);

        // build Response
        $response = $this->container->getServer_ResponseService();
        $response->setHttpVersion($request->getHttpVersion());
        $response->setStatusCode($code, $status);
        $response->addHeaders($headers);
        $response->setBody($content);

        $event->setReturnValue($response);

        return true;
    }
}
