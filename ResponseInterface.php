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
 * @subpackage Response
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
interface ResponseInterface
{
    /**
     * @param string $httpVersion
     */
    function setHttpVersion($httpVersion);

    /**
     * @param integer $statusCode
     * @param string $statusText (optional)
     */
    function setStatusCode($statusCode, $statusText = null);

    /**
     * @param string $name
     * @param mixed $value
     */
    function setHeader($name, $value);

    /**
     * @param array $headers
     */
    function setHeaders(array $headers);

    /**
     * @param array $headers
     */
    function addHeaders(array $headers);

    /**
     * @param string $body
     */
    function setBody($body);

    /**
     * @return string
     */
    function toString();

    // http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
    function isInvalid();
    function isInformational();
    function isSuccessful();
    function isRedirection();
    function isClientError();
    function isServerError();
    function isOk();
    function isForbidden();
    function isNotFound();
    function isRedirect();
    function isEmpty();
}
