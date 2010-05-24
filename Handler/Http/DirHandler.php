<?php

namespace Bundle\ServerBundle\Handler\Http;

use Symfony\Components\EventDispatcher\EventDispatcher,
    Symfony\Components\EventDispatcher\Event,
    Bundle\ServerBundle\Handler\HttpHandler,
    Symfony\Components\Finder\Finder;

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
class DirHandler extends HttpHandler
{
    protected $documentRoot;

    /**
     * @param string $documentRoot
     */
    public function __construct($documentRoot)
    {
        $this->documentRoot = $documentRoot;
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
        $finder = new Finder();
    }
}
