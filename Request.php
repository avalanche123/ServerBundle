<?php

namespace Bundle\ServerBundle;

use Bundle\ServerBundle\RequestInterface,
    Symfony\Components\HttpKernel\ParameterBag;

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
class Request implements RequestInterface, \Serializable
{
    protected $httpVersion;
    protected $requestMethod;
    protected $requestUrl;
    protected $headers;
    protected $body;

    const HTTP_10 = '1.0';
    const HTTP_11 = '1.1';

    const METHOD_HEAD    = 'HEAD';
    const METHOD_GET     = 'GET';
    const METHOD_POST    = 'POST';
    const METHOD_PUT     = 'PUT';
    const METHOD_DELETE  = 'DELETE';
    const METHOD_TRACE   = 'TRACE';
    const METHOD_OPTIONS = 'OPTIONS';
    const METHOD_CONNECT = 'CONNECT';
    const METHOD_PATCH   = 'PATCH';

    /**
     * @param string $message (optional)
     */
    public function __construct($message = null)
    {
        $this->httpVersion   = self::HTTP_11;
        $this->requestMethod = self::METHOD_GET;
        $this->requestUrl    = '/';
        $this->headers       = new ParameterBag();
        $this->body          = null;

        if (null !== $message) {
            $this->fromString($message);
        }
    }

    /**
     * @return array
     */
    static public function getRequestMethods()
    {
        return array(
            self::METHOD_HEAD, self::METHOD_GET, self::METHOD_POST,
            self::METHOD_PUT, self::METHOD_DELETE, self::METHOD_TRACE,
            self::METHOD_OPTIONS, self::METHOD_CONNECT, self::METHOD_PATCH
        );
    }

    /**
     * @return array
     */
    static public function getHttpVersions()
    {
        return array(self::HTTP_10, self::HTTP_11);
    }

    /**
     * @return string
     */
    public function getHttpVersion()
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getRequestMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getRequestUrl()
    {
        return $this->url;
    }

    /**
     * @param string $name
     * @return ParameterBag
     */
    public function getHeader($name)
    {
        return $this->headers->get($name);
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers->all();
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param string $message
     *
     * @throws \InvalidArgumentException If the message is not a valid HTTP request
     * @throws \InvalidArgumentException If the 'Host' header is not set on a HTTP/1.1 request
     */
    public function fromString($message)
    {
        // pecl_http extension
        if (extension_loaded('http')) {
            $message = new \HttpMessage($message);

            if ($message->getType() != HTTP_MSG_REQUEST) {
                throw new \InvalidArgumentException('Message is not a valid HTTP request');
            }

            $this->httpVersion   = $message->getHttpVersion();
            $this->requestMethod = $message->getRequestMethod();
            $this->requestUrl    = $message->getRequestUrl();
            $this->headers->replace($message->getHeaders());
            $this->body          = $message->getBody();
        } else {
            // request pattern
            $pattern = sprintf("/(%s) (.*?) HTTP\/(%s)\r\n/i",
                implode('|', self::getRequestMethods()),
                implode('|', self::getHttpVersions())
            );

            // parse request
            if (false === preg_match($pattern, $message, $matches)) {
                throw new \InvalidArgumentException('Message is not a valid HTTP request');
            }

            // assign request variables
            list(, $this->requestMethod, $this->requestUrl, $this->httpVersion) = $matches;

            // empty body check
            $headers = $message;
            if (false !== $bodyPos = strpos($message, "\r\n\r\n")) {
                $requestPos = strpos($message, "\r\n");
                $headers    = trim(substr($message, $requestPos, $bodyPos));
            }

            // header pattern
            $pattern = "/(.*?: .*?\r\n)/m";

            // parse headers
            if (false !== preg_match_all($pattern, $headers, $matches)) {
                $headers = array();
                foreach ($matches[0] as $header) {
                    list($name, $value) = explode(':', $header);
                    $headers[$name] = trim($value);
                }

                $this->headers->replace($headers);
            }

            // parse body
            if (false !== $bodyPos) {
                // @TODO add decompression of body if needed
                $this->body = ltrim(substr($message, $bodyPos));
            }
        }

        // HTTP 1.1 check for 'Host' header
        if (self::HTTP_11 == $this->httpVersion && !$this->headers->has('Host')) {
            throw new \InvalidArgumentException('All headers except "Host" are optional in the HTTP/1.1');
        }
    }

    /**
     * @see \Serializable
     */
    public function serialize()
    {
        return serialize(array($this->httpVersion, $this->requestMethod, $this->requestUrl, $this->headers->all(), $this->body));
    }

    /**
     * @see \Serializable
     */
    public function unserialize($serialized)
    {
        list($this->httpVersion, $this->requestMethod, $this->requestUrl, $headers, $this->body) = unserialize($serialized);

        $this->headers->replace($headers);
    }
}
