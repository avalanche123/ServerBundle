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
    protected $level;

    /**
     * @param boolean $enabled
     * @param integer $level (optional)
     *
     * @throws \RuntimeException If compression is enabled but gz* function are not available
     */
    public function __construct($enabled = false, $level = -1)
    {
        $this->enabled   = $enabled;
        $this->encodings = array();
        $this->level     = $level;

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
     * @param Symfony\Components\EventDispatcher\EventDispatcher $dispatcher
     */
    public function register(EventDispatcher $dispatcher)
    {
        if (true === $this->enabled) {
            $dispatcher->connect('server.response', array($this, 'filter'));
        }
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
        $request = $event->getSubject();

        if ($request->hasHeader('Accept-Encoding')) {
            foreach ($request->splitHttpAcceptHeader($request->getHeader('Accept-Encoding')) as $encoding) {
                $encoding = strtolower($encoding);

                if (in_array($encoding, $this->encodings)) {
                    $response->setBody($compressed = call_user_func(array($this, $encoding), $body = $response->getBody()));
                    $response->setHeader('Content-Encoding', $encoding);
                    $response->setHeader('Content-Length', $length = strlen($compressed));

                    // add compression statistics
                    $event->setParameter('compression', true);
                    $event->setParameter('compression.encoding', $encoding);
                    $event->setParameter('compression.level', $this->level);
                    $event->setParameter('compression.before', strlen($body));
                    $event->setParameter('compression.after',  $length);

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
        return bzcompress($data, $this->level);
    }

    /**
     * UNIX "compress" programm.
     *
     * @param string $data
     * @return string
     */
    protected function compress($data)
    {
        return gzcompress($data, $this->level);
    }

    /**
     * zlib format with "deflate" compression.
     *
     * @param string $data
     * @return string
     */
    protected function deflate($data)
    {
        return gzdeflate($data, $this->level);
    }

    /**
     * GNU zip format.
     *
     * @param string $data
     * @return string
     */
    protected function gzip($data)
    {
        return gzencode($data, $this->level);
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
