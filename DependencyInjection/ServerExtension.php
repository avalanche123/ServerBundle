<?php

namespace Bundle\ServerBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension,
    Symfony\Components\DependencyInjection\Loader\XmlFileLoader,
    Symfony\Components\DependencyInjection\BuilderConfiguration;

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
 * @subpackage DependencyInjection
 * @author     Pierre Minnieur <pm@pierre-minnieur.de>
 */
class ServerExtension extends LoaderExtension
{
    protected $resources = array(
        'daemon' => 'daemon.xml',
        'server' => 'server.xml'
    );

    /**
     * @param array $config
     * @return BuilderConfiguration
     */
    public function daemonLoad($config)
    {
        $configuration = new BuilderConfiguration();

        $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
        $configuration->merge($loader->load($this->resources['daemon']));

        if (isset($config['pid_file'])) {
            $configuration->setParameter('daemon.pid_file', $config['pid_file']);
        }

        if (isset($config['user'])) {
            $configuration->setParameter('daemon.user', $config['user']);
        }

        if (isset($config['group'])) {
            $configuration->setParameter('daemon.group', $config['group']);
        }

        if (isset($config['umask'])) {
            $configuration->setParameter('daemon.umask', $config['umask']);
        }

        return $configuration;
    }

    /**
     * @param array $config
     * @return BuilderConfiguration
     */
    public function serverLoad($config)
    {
        $configuration = new BuilderConfiguration();

        $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
        $configuration->merge($loader->load($this->resources['server']));

        if (isset($config['protocol'])) {
            $configuration->setParameter('server.protocol', $config['protocol']);
        }

        if (isset($config['address'])) {
            $configuration->setParameter('server.address', $config['address']);
        }

        if (isset($config['port'])) {
            $configuration->setParameter('server.port', $config['port']);
        }

        if (isset($config['max_clients'])) {
            $configuration->setParameter('server.max_clients');
        }

        if (isset($config['max_requests_per_child'])) {
            $configuration->setParameter('server.max_requests_per_child', $config['max_requests_per_child']);
        }

        if (isset($config['document_root'])) {
            $configuration->setParameter('server.document_root', $config['document_root']);
        }

        if (isset($config['compression'])) {
            $configuration->setParameter('server.compression', $config['compression']);
        }

        return $configuration;
    }

    /**
     * @return string
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/';
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/server';
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return 'server';
    }
}
