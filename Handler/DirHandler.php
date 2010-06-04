<?php

namespace Bundle\ServerBundle\Handler;

use Bundle\ServerBundle\Handler\HandlerInterface,
    Bundle\ServerBundle\Response,
    Symfony\Components\EventDispatcher\EventDispatcher,
    Symfony\Components\EventDispatcher\Event,
    Symfony\Components\Finder\Finder;

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
 * @subpackage Handler
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class DirHandler implements HandlerInterface
{
    protected $documentRoot;

    /**
     * @param string $documentRoot
     */
    public function __construct($documentRoot)
    {
        $this->documentRoot = realpath($documentRoot);
    }

    /**
     * @param EventDispatcher $dispatcher
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('server.request', array($this, 'handle'));
    }

    /**
     * @param Event $event
     *
     * @see EventDispatcher::notifyUntil()
     */
    public function handle(Event $event)
    {
        $request = $event->getSubject();

        $url  = trim($request->getRequestUrl(), '/');
        $name = basename($url);
        $dir  = trim(substr($url, 0, strlen($url) - strlen($name)), '/');

        $path = $this->documentRoot.'/'.($dir == $name ? $name : $dir.'/'.$name);
        $path = realpath($path);

        // skip document root
        if ($path == $this->documentRoot) {
            return false;
        }

        // path hacking
        if (substr($path, 0, strlen($this->documentRoot)) !== $this->documentRoot) {
            return false;
        }

        $headers = array();

        // add Date header
        $date = new \DateTime();
        $headers['Date'] = $date->format(DATE_RFC822);

        $path = new \SplFileInfo($path);

        // is file
        if ($path->isFile()) {
            // get mime type
            $info = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($info, $path);
            finfo_close($info);

            // get mime encoding
            $info = finfo_open(FILEINFO_MIME_ENCODING);
            $encoding = finfo_file($info, $path);
            finfo_close($info);

            // add Content-Type/Encoding header
            $headers['Content-Type']     = $mime;
            $headers['Content-Encoding'] = $encoding;

            // add Content-Length header
            $headers['Content-Length'] = filesize($path);

            // build Response
            $response = new Response($request);
            $response->setHttpVersion($request->getHttpVersion());
            $response->setStatusCode(200);
            $response->addHeaders($headers);
            $response->setBody(file_get_contents($path));

            $event->setReturnValue($response);

            return true;
        }

        // is dir
        if ($path->isDir()) {
            $dir = trim(substr($path, strlen($this->documentRoot)), '/');

            $finder = new Finder();
            $finder->depth(0);

            // @TODO add ExceptionHandler-like rendering
            $item = <<<EOF
<tr>
<td valign="top">%s</td>
<td valign="top"><a href="/%s">%s</a></td>
<td align="right">%s</td>
<td align="right">%s%s</td>
</tr>
EOF;

            $list = array();

            if ($path != $this->documentRoot) {
                $list[] = sprintf($item,
                    'parent',
                    $dir.'/../',
                    'Parent directory',
                    '',
                    '-',
                    ''
                );
            }

            foreach ($finder->in($path) as $file) {
                $date = new \DateTime();
                $date->setTimestamp($file->getMTime());

                $list[] = sprintf($item,
                    $file->getType(),
                    ltrim(substr($file->getRealpath(), strlen($this->documentRoot)), '/'),
                    $file->getFilename(),
                    $date->format('d-M-Y H:i'),
                    $file->getSize(),
                    ''
                );
            }

            $layout = <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
 <head>
  <title>Index of /$dir</title>
 </head>
 <body>
<h1>Index of /$dir</h1>
<table><thead>
<tr><th></th><th>Name</th><th>Last modified</th><th>Size</th></tr>
</thead>
<tbody>%s</tbody>
</table>
</body></html>
EOF;

            $content = sprintf($layout, implode("\n", $list));

            // add Content-Type header
            $headers['Content-Type'] = 'text/html; charset=UTF-8';

            // add Content-Length header
            $headers['Content-Length'] = strlen($content);

            // build Response
            $response = new Response($request);
            $response->setHttpVersion($request->getHttpVersion());
            $response->setStatusCode(200);
            $response->addHeaders($headers);
            $response->setBody($content);

            $event->setReturnValue($response);

            return true;
        }

        return false;
    }
}
