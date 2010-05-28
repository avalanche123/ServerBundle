<?php

namespace Bundle\ServerBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension,
    Symfony\Components\DependencyInjection\ContainerInterface,
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
    protected $container;
    protected $resources = array(
        'daemon' => 'daemon.xml',
        'server' => 'server.xml'
    );

    /**
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param array $config
     * @return BuilderConfiguration
     *
     * @throws \InvalidArgumentException If Daemon class does not implement DaemonInterface
     */
    public function daemonLoad($config)
    {
        $configuration = new BuilderConfiguration();

        $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
        $configuration->merge($loader->load($this->resources['daemon']));

        if (isset($config['class'])) {
            $r = new \ReflectionClass($config['class']);

            if (!$r->implementsInterface($interface = 'Bundle\\ServerBundle\\DaemonInterface')) {
                throw new \InvalidArgumentException(sprintf('Daemon class "%s" must implement "%s"', $config['class'], $interface));
            }

            $configuration->setParameter('daemon.class', $config['class']);
        }

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
     *
     * @throws \InvalidArgumentException If Server class does not implement ServerInterface
     */
    public function serverLoad($config)
    {
        $configuration = new BuilderConfiguration();

        $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
        $configuration->merge($loader->load($this->resources['server']));

        if (isset($config['class'])) {
            $r = new \ReflectionClass($config['class']);

            if (!$r->implementsInterface($interface = 'Bundle\\ServerBundle\\ServerInterface')) {
                throw new \InvalidArgumentException(sprintf('Server class "%s" must implement "%s"', $config['class'], $interface));
            }

            $configuration->setParameter('daemon.class', $config['class']);
        }

        if (isset($config['environment'])) {
            $configuration->setParameter('server.kernel_environment', $config['environment']);

            // fixes class redeclaration error on custom kernel environment
            if ($config['environment'] != $this->container->getParameter('kernel.environment')) {
                $configuration->setParameter('kernel.include_core_classes', false);
            }
        } else {
            $configuration->setParameter('server.kernel_environment', $this->container->getParameter('kernel.environment'));
        }

        if (isset($config['debug'])) {
            $configuration->setParameter('server.kernel_debug', $config['debug']);

            // fixes class redeclaration error on custom kernel debug mode
            if ($config['debug'] != $this->container->getParameter('kernel.debug')) {
                $configuration->setParameter('kernel.include_core_classes', false);
            }
        } else {
            $configuration->setParameter('server.kernel_debug', $this->container->getParameter('kernel.debug'));
        }

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
