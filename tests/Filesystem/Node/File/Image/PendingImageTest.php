<?php

/*
 * This file is part of the zenstruck/filesystem package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Tests\Filesystem\Node\File\Image;

use Intervention\Image\Image as InterventionImage;
use Zenstruck\Filesystem\Node\File\Image;
use Zenstruck\Filesystem\Node\File\Image\PendingImage;
use Zenstruck\TempFile;
use Zenstruck\Tests\Filesystem\Node\File\ImageTests;
use Zenstruck\Tests\Filesystem\Node\File\PendingFileTest;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class PendingImageTest extends PendingFileTest
{
    use ImageTests;

    public function can_get_transform_url(): void
    {
        $this->markTestSkipped('Transformation url not supported.');
    }

    /**
     * @test
     */
    public function can_transform_in_place(): void
    {
        $image = $this->createPendingFile(TempFile::for(fixture('symfony.png')), 'noop');

        $this->assertSame(563, $image->dimensions()->width());

        $transformed = $image->transformInPlace(fn(InterventionImage $image) => $image->widen(100));

        $this->assertSame($transformed, $image);
        $this->assertSame(100, $image->dimensions()->width());
    }

    protected function pendingFileClass(): string
    {
        return PendingImage::class;
    }

    protected function createPendingFile(\SplFileInfo $file, string $filename): PendingImage
    {
        return new PendingImage($file);
    }

    protected function createFile(\SplFileInfo $file, string $path): Image
    {
        return parent::createFile($file, $path)->ensureImage();
    }
}
