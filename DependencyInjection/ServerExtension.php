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
     * @throws \InvalidArgumentException If Server class does not implement ServerInterface
     */
    public function serverLoad($config)
    {
        $configuration = new BuilderConfiguration();

        $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
        $configuration->merge($loader->load($this->resources['server']));

        // Options
        $options = array(
            'pid_file', 'user', 'group', 'umask', 'hostname', 'admin',
            'hostname_lookups', 'max_clients', 'max_requests_per_child',
            'address', 'port', 'timeout', 'keepalive_timeout',
        );

        // General
        foreach ($options as $name) {
            if (!array_key_exists($name, $config)) {
                continue;
            }

            $configuration->setParameter(sprintf('server.%s', $name), $config[$name]);
        }

        // Classes
        if (isset($config['class'])) {
            $this->checkServiceClassInterface('Server', $config['class'], 'Bundle\\ServerBundle\\ServerInterface');

            $configuration->setParameter('server.class', $config['class']);
        }
        if (isset($config['request'])) {
            $this->checkServiceClassInterface('Request', $config['request'], 'Bundle\\ServerBundle\\RequestInterface');

            $configuration->setParameter('server.request.class', $config['request']);
        }
        if (isset($config['response'])) {
            $this->checkServiceClassInterface('Response', $config['response'], 'Bundle\\ServerBundle\\ResponseInterface');

            $configuration->setParameter('server.response.class', $config['response']);
        }

        // Handlers
        if (isset($config['handlers'])) {
            if (!is_array($config['handlers'])) {
                throw new \InvalidArgumentException(sprintf('Handler configuration must be of type array, "%s" given', gettype($config['handlers'])));
            }

            foreach ($config['handlers'] as $handler) {
                // @TODO: configure handlers
                // $this->checkServiceClassInterface('Handler', $handler['class'], 'Bundle\\ServerBundle\\Handler\\HandlerInterface');
            }
        }

        // Handler configuration
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

        // Filters
        if (isset($config['filters'])) {
            if (!is_array($config['filters'])) {
                throw new \InvalidArgumentException(sprintf('Filter configuration must be of type array, "%s" given', gettype($config['filters'])));
            }

            foreach ($config['filters'] as $filter) {
                // @TODO: configure filters
                // $this->checkServiceClassInterface('Filter', $filter['class'], 'Bundle\\ServerBundle\\Filter\\FilterInterface');
            }
        }

        // Filter configuration
        if (isset($config['compression'])) {
            $configuration->setParameter('server.compression', $config['compression']);
        }

        return $configuration;
    }

    /**
     * @param string $service
     * @param string $class
     * @param string $interface
     * @return boolean
     *
     * @throws \InvalidArgumentException If the class does not implement the interface
     */
    protected function checkServiceClassInterface($service, $class, $interface)
    {
        $r = new \ReflectionClass($class);

        if (!$r->implementsInterface($interface)) {
            throw new \InvalidArgumentException(sprintf('%s class "%s" must implement "%s"', $service, $class, $interface));
        }

        return true;
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
