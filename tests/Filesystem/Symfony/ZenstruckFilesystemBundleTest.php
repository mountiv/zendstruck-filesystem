<?php

/*
 * This file is part of the zenstruck/filesystem package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Tests\Filesystem\Symfony;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Filesystem\Node\File\Path\Expression;
use Zenstruck\Filesystem\Test\InteractsWithFilesystem;
use Zenstruck\Filesystem\Test\ResetFilesystem;
use Zenstruck\Tests\Filesystem\Symfony\Fixture\Service;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class ZenstruckFilesystemBundleTest extends KernelTestCase
{
    use InteractsWithFilesystem, ResetFilesystem;

    /**
     * @test
     */
    public function filesystem_services_autowired(): void
    {
        /** @var Service $service */
        $service = self::getContainer()->get(Service::class);

        $this->assertSame($service->filesystem, $service->publicFilesystem);
        $this->assertNotSame($service->filesystem, $service->privateFilesystem);

        $service->multiFilesystem->write('public://file1.txt', 'public content');
        $service->multiFilesystem->write('private://file2.txt', 'private content');

        $this->assertTrue($service->multiFilesystem->has('file1.txt'));
        $this->assertTrue($service->multiFilesystem->has('public://file1.txt'));
        $this->assertFalse($service->publicFilesystem->has('file2.txt'));
        $this->assertTrue($service->publicFilesystem->has('file1.txt'));
        $this->assertTrue($service->privateFilesystem->has('file2.txt'));
        $this->assertFalse($service->privateFilesystem->has('file1.txt'));
    }

    /**
     * @test
     */
    public function files_are_created_in_proper_spots(): void
    {
        /** @var Service $service */
        $service = self::getContainer()->get(Service::class);
        $varDir = \dirname(TEMP_DIR);

        $this->assertFileDoesNotExist($publicFile = $varDir.'/public/file1.txt');
        $this->assertFileDoesNotExist($privateFile = $varDir.'/private/file2.txt');
        $this->assertFileDoesNotExist($noResetFile = $varDir.'/no_reset/file3.txt');

        $service->publicFilesystem->write('file1.txt', 'content');
        $service->privateFilesystem->write('file2.txt', 'content');
        $service->noResetFilesystem->write('file3.txt', 'content');

        $this->assertFileExists($publicFile);
        $this->assertFileExists($privateFile);
        $this->assertFileExists($noResetFile);
    }

    /**
     * @test
     */
    public function can_autowire_path_generator(): void
    {
        /** @var Service $service */
        $service = self::getContainer()->get(Service::class);

        $file = in_memory_filesystem()->write('some/file.txt', 'content')->last()->ensureFile();

        $this->assertSame('9a0364b.txt', $service->pathGenerator->generate(Expression::checksum(7), $file));
    }

    /**
     * @test
     */
    public function can_generate_urls(): void
    {
        $publicFile = $this->filesystem()->write('public://foo/file.png', 'content')->last()->ensureImage();

        $this->assertSame('/prefix/foo/file.png', $publicFile->publicUrl());
        $this->assertStringContainsString('/temp/foo/file.png', $publicFile->temporaryUrl('tomorrow'));
        $this->assertStringContainsString('_hash=', $publicFile->temporaryUrl('tomorrow'));
        $this->assertStringContainsString('_expires=', $publicFile->temporaryUrl('tomorrow'));
        $this->assertSame('http://localhost/transform/foo/file.png?filter=grayscale', $publicFile->transformUrl('grayscale'));
        $this->assertSame('http://localhost/transform/foo/file.png?w=100&h=200', $publicFile->transformUrl(['w' => 100, 'h' => 200]));

        $privateFile = $this->filesystem()->write('private://bar/file.png', 'content')->last()->ensureImage();

        $this->assertStringContainsString('http://localhost/private/bar/file.png', $privateFile->publicUrl());
        $this->assertStringContainsString('_hash=', $privateFile->publicUrl());
        $this->assertStringNotContainsString('_expires=', $privateFile->publicUrl());
        $this->assertStringContainsString('http://localhost/private/bar/file.png', $privateFile->publicUrl(['expires' => 'tomorrow']));
        $this->assertStringContainsString('_hash=', $privateFile->publicUrl(['expires' => 'tomorrow']));
        $this->assertStringContainsString('_expires=', $privateFile->publicUrl(['expires' => 'tomorrow']));
        $this->assertSame('http://localhost/private/bar/file.png', $privateFile->publicUrl(['sign' => false]));
        $this->assertStringContainsString('/private/bar/file.png', $privateFile->temporaryUrl('tomorrow'));
        $this->assertStringContainsString('_hash=', $privateFile->temporaryUrl('tomorrow'));
        $this->assertStringContainsString('_expires=', $privateFile->temporaryUrl('tomorrow'));
        $this->assertSame('/glide/bar/file.png?w=100&h=200', $privateFile->transformUrl(['w' => 100, 'h' => 200]));
    }
}
