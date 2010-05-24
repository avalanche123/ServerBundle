<?php

namespace Bundle\ServerBundle\Handler\Http;

use Symfony\Components\HttpKernel\KernelInterface,
    Symfony\Components\EventDispatcher\EventDispatcher,
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
class SymfonyHandler extends HttpHandler
{
    protected $kernel;

    /**
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

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
        // boot kernel?
        // if (!$kernel->isBooted()) {
        //     $kernel->boot();
        // }

        // create fake environment with HttpMessage and Kernel data ...
        // _SERVER, _GET, _POST, _REQUEST, _COOKIE, _FILES, _SESSION?

        // create fake requests with fake environment
        // $request = Request::create( $get, $post, etc ... );

        // dispatch fake requests, main & raw?
        // $response = $kernel->handle($request);

        // shutdown kernel?
        // $kernel->shutdown();

        // @TODO must be clarified how the dispatching of the event works
        // return $event / $response / whatever
    }
}
