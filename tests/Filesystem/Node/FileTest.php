<?php

namespace Zenstruck\Tests\Filesystem\Node;

use PHPUnit\Framework\TestCase;
use Zenstruck\Filesystem\Node\File;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
abstract class FileTest extends TestCase
{
    /**
     * @test
     */
    public function metadata_with_extension(): void
    {
        $file = $this->createFile(fixture('symfony.png'), 'some/file.png');

        $this->assertTrue($file->exists());
        $this->assertSame('some/file.png', $file->path());
        $this->assertSame('file.png', $file->name());
        $this->assertSame('some', $file->directory()->path());
        $this->assertSame('public', $file->visibility());
        $this->assertSame('image/png', $file->mimeType());
        $this->assertSame(\date_default_timezone_get(), $file->lastModified()->getTimezone()->getName());
        $this->assertSame('png', $file->extension());
        $this->assertSame('png', $file->guessExtension());
        $this->assertSame('file', $file->nameWithoutExtension());
        $this->assertSame(10862, $file->size());
        $this->assertSame('ac6884fc84724d792649552e7211843a', $file->checksum());
        $this->assertSame('ad94b8d14313713d2c8ac619f1d055bc499e3cd0', $file->checksum('sha1'));
    }

    /**
     * @test
     */
    public function metadata_without_extension(): void
    {
        $file = $this->createFile(fixture('symfony.png'), 'some/file');

        $this->assertNull($file->extension());
        $this->assertSame('png', $file->guessExtension());
        $this->assertSame('image/png', $file->mimeType());
    }

    /**
     * @test
     */
    public function file_urls(): void
    {
        $file = $this->createFile(fixture('symfony.png'), 'some/file.png');

        $this->assertSame('/prefix/some/file.png', $file->publicUrl());
        $this->assertSame('/temp/some/file.png?expires=1640995200', $file->temporaryUrl(new \DateTime('2022-01-01')));
    }

    /**
     * @test
     */
    public function file_content(): void
    {
        $file = $this->createFile($fixture = fixture('symfony.png'), 'some/file.png');

        $expected = \file_get_contents($fixture);

        $this->assertSame($expected, $file->contents());
        $this->assertSame($expected, $file->read()->contents());
        $this->assertSame($expected, \file_get_contents($file->tempFile()));
    }

    /**
     * @test
     */
    public function metadata_cached(): void
    {
        $file = $this->createFile(fixture('symfony.png'), 'some/file');

        $this->assertSame($originalMime = 'image/png', $file->mimeType());
        $this->assertSame($originalSize = 10862, $file->size());
        $this->assertSame($originalChecksum = 'ac6884fc84724d792649552e7211843a', $file->checksum());
        $this->assertSame($originalSha1Checksum = 'ad94b8d14313713d2c8ac619f1d055bc499e3cd0', $file->checksum('sha1'));

        $this->modifyFile($file, fixture('symfony.jpg'));

        $this->assertSame($originalMime, $file->mimeType());
        $this->assertSame($originalSize, $file->size());
        $this->assertSame($originalChecksum, $file->checksum());
        $this->assertSame($originalSha1Checksum, $file->checksum('sha1'));

        $this->assertSame($file, $file->refresh());

        $this->assertSame('image/jpeg', $file->mimeType());
        $this->assertSame(25884, $file->size());
        $this->assertSame('42890a25562a1803949caa09d235f242', $file->checksum());
        $this->assertSame('4dadf4a29cdc3b57ab8564f5651b30e236ca536d', $file->checksum('sha1'));
    }

    abstract protected function modifyFile(File $file, \SplFileInfo $new): void;

    abstract protected function createFile(\SplFileInfo $file, string $path): File;
}
