<?php

/*
 * This file is part of the zenstruck/filesystem package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Filesystem\Symfony\DependencyInjection;

use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Zenstruck\Filesystem;
use Zenstruck\Filesystem\Doctrine\EventListener\NodeLifecycleListener;
use Zenstruck\Filesystem\Doctrine\EventListener\NodeMappingListener;
use Zenstruck\Filesystem\Doctrine\ObjectFileLoader;
use Zenstruck\Filesystem\Flysystem\AdapterFactory;
use Zenstruck\Filesystem\FlysystemFilesystem;
use Zenstruck\Filesystem\LoggableFilesystem;
use Zenstruck\Filesystem\MultiFilesystem;
use Zenstruck\Filesystem\Node\File\Path\ExpressionPathGenerator;
use Zenstruck\Filesystem\Node\File\Path\Generator;
use Zenstruck\Filesystem\Node\File\PathGenerator;
use Zenstruck\Filesystem\Symfony\Form\PendingFileType;
use Zenstruck\Filesystem\Symfony\HttpKernel\FilesystemDataCollector;
use Zenstruck\Filesystem\Symfony\Serializer\NodeNormalizer;
use Zenstruck\Filesystem\TraceableFilesystem;
use Zenstruck\Filesystem\Twig\TwigPathGenerator;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class ZenstruckFilesystemExtension extends ConfigurableExtension
{
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $locator = $container->register('zenstruck_filesystem.filesystem_locator', ServiceLocator::class)
            ->addArgument(new TaggedIteratorArgument('zenstruck_filesystem', 'key'))
            ->addTag('container.service_locator')
        ;

        $multi = $container->register(MultiFilesystem::class)
            ->addArgument(new Reference('zenstruck_filesystem.filesystem_locator'))
        ;

        if ('test' === $container->getParameter('kernel.environment')) {
            $locator->setPublic(true);
            $multi->setPublic(true);
        }

        if ($container->getParameter('kernel.debug')) {
            $container->register('.zenstruck_filesystem.data_collector', FilesystemDataCollector::class)
                ->addTag('data_collector', [
                    'template' => '@ZenstruckFilesystem/Collector/filesystem.html.twig',
                    'id' => 'filesystem',
                ])
            ;
        }

        // form types
        $container->register('.zenstruck_filesystem.form.pending_file_type', PendingFileType::class)
            ->addTag('form.type')
        ;

        // normalizer
        $container->register('.zenstruck_filesystem.node_normalizer', NodeNormalizer::class)
            ->addArgument(new Reference('zenstruck_filesystem.filesystem_locator'))
            ->addTag('serializer.normalizer')
        ;

        $this->registerFilesystems($mergedConfig, $container);
        $this->registerPathGenerators($container);

        if ($mergedConfig['doctrine']['enabled']) {
            $this->registerDoctrine($container, $mergedConfig['doctrine']);
        }
    }

    private function registerDoctrine(ContainerBuilder $container, array $config): void
    {
        $container->register('.zenstruck_filesystem.doctrine.mapping_listener', NodeMappingListener::class)
            ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata'])
        ;

        $container->register(ObjectFileLoader::class)
            ->setArguments([
                new Reference('doctrine'),
                new Reference('.zenstruck_filesystem.doctrine.lifecycle_listener'),
            ])
        ;

        $listener = $container->register('.zenstruck_filesystem.doctrine.lifecycle_listener', NodeLifecycleListener::class)
            ->addArgument(new ServiceLocatorArgument([
                PathGenerator::class => new Reference(PathGenerator::class),
                'filesystem_locator' => new Reference('zenstruck_filesystem.filesystem_locator'),
            ]))
            ->addTag('doctrine.event_listener', ['event' => 'preUpdate'])
            ->addTag('doctrine.event_listener', ['event' => 'postFlush'])
            ->addTag('doctrine.event_listener', ['event' => 'prePersist'])
        ;

        if ($config['lifecycle']['autoload']) {
            $listener->addTag('doctrine.event_listener', ['event' => 'postLoad']);
        }

        if ($config['lifecycle']['delete_on_remove']) {
            $listener->addTag('doctrine.event_listener', ['event' => 'postRemove']);
        }
    }

    private function registerPathGenerators(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(Generator::class)
            ->addTag('zenstruck_filesystem.path_generator')
        ;

        $container->register(PathGenerator::class)
            ->addArgument(new ServiceLocatorArgument(new TaggedIteratorArgument('zenstruck_filesystem.path_generator', 'key', needsIndexes: true)))
        ;

        $expression = $container->register('.zenstruck_filesystem.path_generator.expression', ExpressionPathGenerator::class)
            ->addTag('zenstruck_filesystem.path_generator', ['key' => 'expression'])
        ;

        if (
            ContainerBuilder::willBeAvailable('symfony/string', SluggerInterface::class, ['symfony/framework-bundle']) &&
            ContainerBuilder::willBeAvailable('symfony/translation', LocaleAwareInterface::class, ['symfony/framework-bundle'])
        ) {
            $expression->addArgument(new Reference('slugger'));
        }

        if (isset($container->getParameter('kernel.bundles')['TwigBundle'])) {
            $container->register('.zenstruck_filesystem.path_generator.twig', TwigPathGenerator::class)
                ->addTag('zenstruck_filesystem.path_generator', ['key' => 'twig'])
            ;
        }
    }

    private function registerFilesystems(array $mergedConfig, ContainerBuilder $container): void
    {
        if (!$mergedConfig['filesystems']) {
            return; // no filesystems defined
        }

        $defaultName = $mergedConfig['default_filesystem'] ?? \array_key_first($mergedConfig['filesystems']);

        if (!isset($mergedConfig['filesystems'][$defaultName])) {
            throw new InvalidConfigurationException('Invalid default filesystem name');
        }

        $container->getDefinition(MultiFilesystem::class)
            ->addArgument($defaultName)
        ;

        foreach ($mergedConfig['filesystems'] as $name => $config) {
            $this->registerFilesystem($name, $config, $container, $defaultName);
        }
    }

    private function registerFilesystem(string $name, array $config, ContainerBuilder $container, string $defaultName): void
    {
        if ($config['reset_before_tests']) {
            if (!$container->hasParameter('zenstruck_filesystem.reset_before_tests_filesystems')) {
                $container->setParameter('zenstruck_filesystem.reset_before_tests_filesystems', []);
            }

            $container->setParameter(
                'zenstruck_filesystem.reset_before_tests_filesystems',
                \array_merge($container->getParameter('zenstruck_filesystem.reset_before_tests_filesystems'), [$name]) // @phpstan-ignore-line
            );
        }

        if (\str_starts_with($config['dsn'], '@')) {
            $config['dsn'] = new Reference(\mb_substr($config['dsn'], 1));
        } else {
            $container->register($adapterId = '.zenstruck_filesystem.flysystem_adapter.'.$name, FilesystemAdapter::class)
                ->setFactory([AdapterFactory::class, 'createAdapter'])
                ->addArgument($config['dsn'])
            ;
            $config['dsn'] = new Reference($adapterId);
        }

        if (isset($config['public_url']['prefix'])) {
            $config['config']['url_prefix'] = $config['public_url']['prefix'];
        }

        $container->register($flysystemId = 'zenstruck_filesystem.flysystem.'.$name, Flysystem::class)
            ->setArguments([$config['dsn'], $config['config']])
        ;

        $container->register($filesystemId = '.zenstruck_filesystem.filesystem.'.$name, FlysystemFilesystem::class)
            ->setArguments([new Reference($flysystemId), $name])
            ->addTag('zenstruck_filesystem', ['key' => $name])
        ;

        if ($config['log']) {
            $container->register('.zenstruck_filesystem.filesystem.log_'.$name, LoggableFilesystem::class)
                ->setDecoratedService($filesystemId)
                ->setArguments([new Reference('.inner'), new Reference('logger')])
                ->addTag('monolog.logger', ['channel' => 'filesystem'])
            ;
        }

        if ($container->getParameter('kernel.debug')) {
            $container->register('.zenstruck_filesystem.filesystem.traceable_'.$name, TraceableFilesystem::class)
                ->setDecoratedService($filesystemId)
                ->setArguments([new Reference('.inner'), new Reference('debug.stopwatch', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag('kernel.reset', ['method' => 'reset'])
            ;

            $container->getDefinition('.zenstruck_filesystem.data_collector')
                ->addMethodCall('addFilesystem', [new Reference('.zenstruck_filesystem.filesystem.traceable_'.$name)])
            ;
        }

        if ($name === $defaultName) {
            $container->setAlias(Filesystem::class, $filesystemId);
        } else {
            $container->registerAliasForArgument($filesystemId, Filesystem::class, $name.'Filesystem');
        }
    }
}
