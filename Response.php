<?php

namespace Bundle\ServerBundle;

use Bundle\ServerBundle\ResponseInterface,
    Bundle\ServerBundle\RequestInterface,
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
    protected $request;
    protected $httpVersion;
    protected $statusCode;
    protected $statusText;
    protected $headers;
    protected $body;

    /**
     * @param Bundle\ServerBundle\RequestInterface $request
     * @param string $httpVersion (optional)
     * @param integer $statusCode (optional)
     * @param string $statusText (optional)
     * @param array $headers (optional)
     * @param string $body (optional)
     */
    public function __construct(RequestInterface $request, $httpVersion = Request::HTTP_11, $statusCode = 200, $statusText = 'OK', array $headers = array(), $body = null)
    {
        $this->request     = $request;
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
    public function getBody()
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
        // HTTP/0.9 (Simple Response)
        if (Request::HTTP_09 == $this->httpVersion) {
            return $this->body;
        }

        // HTTP/1.x (Full Response)
        if ($this->request->getRequestMethod() == Request::METHOD_HEAD ||
            $this->isEmpty() || $this->isInformational()) {

            foreach ($this->headers->all() as $name => $value) {
                if (false !== strpos(strtolower($name), 'content')) {
                    $this->headers->delete($name);
                }
            }

            $this->body = null;
        }

        $message = sprintf("HTTP/%s %d %s\r\n", $this->httpVersion, $this->statusCode, $this->statusText);

        foreach ($this->headers->all() as $name => $value) {
            $message .= sprintf("%s: %s\r\n", trim($name), trim($value));
        }

        // body may be empty
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
        list($this->httpVersion, $this->statusCode, $this->statusText, $headers, $this->body) = unserialize($serialized);

        $this->headers->replace($headers);
    }

    // http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
    public function isInvalid()
    {
        return $this->statusCode < 100 || $this->statusCode >= 600;
    }

    public function isInformational()
    {
        return $this->statusCode >= 100 && $this->statusCode < 200;
    }

    public function isSuccessful()
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isRedirection()
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    public function isClientError()
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError()
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    public function isOk()
    {
        return 200 === $this->statusCode;
    }

    public function isForbidden()
    {
        return 403 === $this->statusCode;
    }

    public function isNotFound()
    {
        return 404 === $this->statusCode;
    }

    public function isRedirect()
    {
        return in_array($this->statusCode, array(301, 302, 303, 307));
    }

    public function isEmpty()
    {
        return in_array($this->statusCode, array(201, 204, 304));
    }
}
