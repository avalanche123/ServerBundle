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

class CompressionFilter extends HttpFilter
{
    protected $enabled;

    /**
     * @param boolean $enabled
     */
    public function __construct($enabled = false)
    {
        $this->enabled = $enabled;
    }

    /**
     * @param EventDispatcher $dispatcher
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('server.response', array($this, 'filter'));
    }

    /**
     * @param Event $event
     */
    public function filter(Event $event)
    {
    }

    /**
     * @param string $data
     * @return string
     */
    protected function deflate($data)
    {
    }

    /**
     * @param string $data
     * @return string
     */
    protected function gzip($data)
    {
    }
}
