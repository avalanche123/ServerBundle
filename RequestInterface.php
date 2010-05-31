<?php

namespace Bundle\ServerBundle;

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
 * @subpackage Request
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
interface RequestInterface
{
    /**
     * @return string
     */
    function getHttpVersion();

    /**
     * @return string
     */
    function getRequestMethod();

    /**
     * @return string
     */
    function getRequestUrl();

    /**
     * @return mixed
     */
    function getHeader($name);

    /**
     * @return array
     */
    function getHeaders();

    /**
     * @return boolean
     */
    function hasHeader($name);

    /**
     * @return string
     */
    function getBody();

    /**
     * @param string $message
     */
    function fromString($message);
}
