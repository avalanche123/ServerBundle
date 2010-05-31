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

class CompressionFilter implements FilterInterface
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
        if (true === $this->enabled) {
            $dispatcher->connect('server.response', array($this, 'filter'));
        }
    }

    /**
     * @param Event $event
     * @param mixed $value
     * @return mixed
     *
     * @see EventDispatcher::filter()
     */
    public function filter(Event $event, ResponseInterface $response)
    {
        // $request = $event->getSubject();

        return $response;

        // parse headers, check which compression is available

        // determine best (deflate > gzip) for available compressions

        // compress data
        // $data = $this->deflate($date);
        // $data = $this->gzip($data)

        // @TODO must be clarified how the dispatching of the event works
        // return $event / $data / whatever
    }

    /**
     * @param string $data
     * @return string
     */
    protected function deflate($data)
    {
      return gzdeflate($data, 9);
    }

    /**
     * @param string $data
     * @return string
     */
    protected function gzip($data)
    {
      return gzcompress($data, 9);
    }
}
