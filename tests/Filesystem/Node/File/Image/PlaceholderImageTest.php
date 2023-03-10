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

use Zenstruck\Filesystem\Node\File\Image\PlaceholderImage;
use Zenstruck\Tests\Filesystem\Node\File\PlaceholderFileTest;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class PlaceholderImageTest extends PlaceholderFileTest
{
    protected function createNode(?string $path = null): PlaceholderImage
    {
        return new PlaceholderImage($path);
    }
}
