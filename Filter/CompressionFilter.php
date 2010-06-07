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
    protected $encodings;

    /**
     * @param boolean $enabled
     *
     * @throws \RuntimeException If compression is enabled but gz* function are not available
     */
    public function __construct($enabled = false)
    {
        $this->enabled   = $enabled;
        $this->encodings = array();

        if (false !== $this->enabled) {
            if (function_exists('gzdeflate')) {
                $this->encodings[] = 'deflate';
            }

            if (function_exists('gzencode')) {
                $this->encodings[] = 'gzip';
            }

            if (count($this->encodings) == 0) {
                throw new \RuntimeException('gz* functions are required');
            }
        }
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
     * @param ResponseInterface $response
     * @return ResponseInterface
     *
     * @see EventDispatcher::filter()
     */
    public function filter(Event $event, ResponseInterface $response)
    {
        $request = $event->getSubject();

        if ($request->hasHeader('Accept-Encoding')) {
            $encodings = explode(',', $request->getHeader('Accept-Encoding'));

            foreach ($encodings as $encoding) {
                $encoding = strtolower($encoding);

                if (in_array($encoding, $this->encodings)) {
                    $response->setBody($compressed = call_user_func(array($this, $encoding), $response->getBody()));
                    $response->setHeader('Content-Encoding', $encoding);
                    $response->setHeader('Content-Length', strlen($compressed));

                    return $response;
                }
            }
        }

        return $response;
    }

    /**
     * @param string $data
     * @return string
     */
    protected function deflate($data)
    {
      return gzdeflate($data /*, $this->level */);
    }

    /**
     * @param string $data
     * @return string
     */
    protected function gzip($data)
    {
      return gzencode($data /*, $this->level */);
    }
}
