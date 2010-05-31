<?php

namespace Bundle\ServerBundle;

use Bundle\ServerBundle\ResponseInterface,
    Bundle\ServerBundle\Request,
    Symfony\Components\HttpKernel\ParameterBag,
    Symfony\Components\HttpKernel\Response as SymfonyResponse;

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
class Response implements ResponseInterface, \Serializable
{
    protected $httpVersion;
    protected $statusCode;
    protected $statusText;
    protected $headers;
    protected $body;

    /**
     * @param string $httpVersion (optional)
     * @param integer $statusCode (optional)
     * @param string $statusText (optional)
     * @param array $headers (optional)
     * @param string $body (optional)
     */
    public function __construct($httpVersion = Request::HTTP_11, $statusCode = 200, $statusText = 'OK', array $headers = array(), $body = null)
    {
        $this->httpVersion = $httpVersion;
        $this->statusCode  = $statusCode;
        $this->statusText  = $statusText;
        $this->headers     = new ParameterBag();
        $this->body        = $body;

        if (count($headers) > 0) {
            $this->headers->replace($headers);
        }
    }

    /**
     * @return string
     */
    public function getHttpVersion()
    {
        return $this->httpVersion;
    }

    /**
     * @param string $httpVersion
     *
     * @throws \InvalidArgumentException If the HTTP version is not valid
     */
    public function setHttpVersion($httpVersion)
    {
        if (!in_array($httpVersion, Request::getHttpVersions())) {
            throw new \InvalidArgumentException(sprintf('HTTP "%s" version is not valid', $httpVersion));
        }

        $this->httpVersion = $httpVersion;
    }

    /**
     * @return integer
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param integer $statusCode
     * @param string $statusText (optional)
     *
     * @throws \InvalidArgumentException If the HTTP status code is not valid
     */
    public function setStatusCode($statusCode, $statusText = null)
    {
        $this->statusCode = (integer) $statusCode;

        if ($this->statusCode < 100 || $this->statusCode > 599) {
            throw new \InvalidArgumentException(sprintf('The HTTP status code "%s" is not valid.', $statusCode));
        }

        $this->statusText = false === $statusText ? '' : (null === $statusText ? SymfonyResponse::$statusTexts[$this->statusCode] : $statusText);
    }

    /**
     * @return string
     */
    public function getStatusText()
    {
        return $this->statusText;
    }

    /**
     * @param string $statusText
     */
    public function setStatusText($statusText)
    {
        $this->statusText = $statusText;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getHeader($name)
    {
        return $this->headers->get($name);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setHeader($name, $value)
    {
        $this->headers->set($name, $value);
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function hasHeader($name)
    {
        return $this->headers->has($name);
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers->all();
    }

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers)
    {
        $this->headers->repace($headers);
    }

    /**
     * @param array $headers
     * @param boolean $replace (optional)
     */
    public function addHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->headers->set($name, $value);
        }
    }

    /**
     * @param string $name
     */
    public function deleteHeader($name)
    {
        $this->headers->delete($name);
    }

    /**
     * @param string $body
     */
    public function getBody($body)
    {
        return $this->body;
    }

    /**
     * @param string $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * @return string
     */
    public function toString()
    {
        // pecl_http extension
        if (extension_loaded('http')) {
            $message = new \HttpMessage();

            $message->setType(HTTP_MSG_RESPONSE);
            $message->setHttpVersion($this->httpVersion);
            $message->setResponseCode($this->statusCode);
            $message->setResponseStatus($this->statusText);
            $message->setHeaders($this->headers->all());
            $message->setBody($this->body);

            return $message->toString();
        }

        $message = sprintf("HTTP/%s %d %s\r\n", $this->httpVersion, $this->statusCode, $this->statusText);

        foreach ($this->headers->all() as $name => $value) {
            $message .= sprintf("%s: %s\r\n", trim($name), trim($value));
        }

        $message .= "\r\n".$this->body;

        return $message;
    }

    /**
     * @see Response::toString()
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @see \Serializable
     */
    public function serialize()
    {
        return serialize(array($this->httpVersion, $this->statusCode, $this->statusText, $this->headers->all(), $this->body));
    }

    /**
     * @see \Serializable
     */
    public function unserialize($serialized)
    {
        list($this->httpVersion, $this->statusCode, $this->statusText, $headers, $this->body) = unserialize($data);

        $this->headers->replace($headers);
    }
}
