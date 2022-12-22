<?php

/*
 * This file is part of the zenstruck/filesystem package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Tests\Filesystem\Symfony\Fixture;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Zenstruck\Filesystem\Symfony\Form\PendingFileType;
use Zenstruck\Filesystem\Symfony\ZenstruckFilesystemBundle;
use Zenstruck\Foundry\ZenstruckFoundryBundle;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function submitForm(Request $request, FormFactoryInterface $factory): Response
    {
        $form = $factory->createBuilder()
            ->add('file', PendingFileType::class, [
                'image' => $request->query->has('image'),
                'multiple' => $request->query->has('multiple'),
            ])
            ->getForm()
            ->submit($request->files->all())
        ;

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new Response('Not submitted and valid.');
        }

        $file = $form->getData()['file'];

        if (\is_array($file)) {
            foreach ($file as $f) {
                if (!\file_exists($f)) {
                    return new Response('File does not exist.');
                }
            }

            return new JsonResponse(\array_map(fn($f) => \get_debug_type($f), $file));
        }

        if (!\file_exists($file)) {
            return new Response('File does not exist.');
        }

        return new Response(\get_debug_type($file));
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new TwigBundle();
        yield new ZenstruckFoundryBundle();
        yield new ZenstruckFilesystemBundle();
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader): void
    {
        $c->loadFromExtension('framework', [
            'http_method_override' => false,
            'secret' => 'S3CRET',
            'router' => ['utf8' => true],
            'test' => true,
        ]);

        $c->loadFromExtension('twig', [
            'default_path' => __DIR__.'/templates',
        ]);

        $c->loadFromExtension('zenstruck_foundry', [
            'auto_refresh_proxies' => false,
        ]);

        $c->loadFromExtension('doctrine', [
            'dbal' => ['url' => 'sqlite:///%kernel.project_dir%/var/data.db'],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'auto_mapping' => true,
                'mappings' => [
                    'Test' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => '%kernel.project_dir%/tests/Filesystem/Symfony/Fixture/Entity',
                        'prefix' => 'Zenstruck\Tests\Filesystem\Symfony\Fixture\Entity',
                        'alias' => 'Test',
                    ],
                ],
            ],
        ]);

        $c->loadFromExtension('zenstruck_filesystem', [
            'filesystems' => [
                'public' => [
                    'dsn' => '%kernel.project_dir%/var/public',
                    'url_prefix' => '/files',
                    'reset_before_tests' => true,
                ],
                'private' => [
                    'dsn' => '%kernel.project_dir%/var/private',
                    'reset_before_tests' => true,
                ],
                'no_reset' => [
                    'dsn' => '%kernel.project_dir%/var/no_reset',
                ],
            ],
        ]);

        $c->register(Service::class)
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;
        $c->register(CustomPathGenerator::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('submit_form', '/submit-form')
            ->controller([$this, 'submitForm'])
        ;
    }
}
