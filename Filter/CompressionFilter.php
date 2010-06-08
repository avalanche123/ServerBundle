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
            // bzip2
            if (function_exists('bzcompress')) {
                $this->encodings[] = 'bzip2';
            }

            // compress
            if (function_exists('gzcompress')) {
                $this->encodings[] = 'compress';
            }

            // deflate
            if (function_exists('gzdeflate')) {
                $this->encodings[] = 'deflate';
            }

            // gzip
            if (function_exists('gzencode')) {
                $this->encodings[] = 'gzip';
            }

            // LZF
            if (function_exists('lzf_compress')) {
                $this->encodings[] = 'lzf';
            }

            // disable if no encoding is available
            if (count($this->encodings) == 0) {
                $this->enabled = false;
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
            foreach ($request->splitHttpAcceptHeader($request->getHeader('Accept-Encoding')) as $encoding) {
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
     * bzip2 format.
     *
     * @param string $data
     * @return string
     */
    protected function bzip2($data)
    {
        return bzcompress($data /*, $this->level */);
    }

    /**
     * UNIX "compress" programm.
     *
     * @param string $data
     * @return string
     */
    protected function compress($data)
    {
        return gzcompress($data /*, $this->level */);
    }

    /**
     * zlib format with "deflate" compression.
     *
     * @param string $data
     * @return string
     */
    protected function deflate($data)
    {
        return gzdeflate($data /*, $this->level */);
    }

    /**
     * GNU zip format.
     *
     * @param string $data
     * @return string
     */
    protected function gzip($data)
    {
        return gzencode($data /*, $this->level */);
    }

    /**
     * LZF compression.
     *
     * @param string $data
     * @return string
     */
    protected function lzf($data)
    {
        return lzf_compress($data);
    }
}
