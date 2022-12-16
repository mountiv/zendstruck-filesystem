<?php

/*
 * This file is part of the zenstruck/filesystem package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Tests\Filesystem\Multi;

use Zenstruck\Filesystem\MultiFilesystem;
use Zenstruck\Tests\Filesystem\MultiFilesystemTest;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class ArrayMultiFilesystemTest extends MultiFilesystemTest
{
    protected function createMultiFilesystem(array $filesystems, ?string $default = null): MultiFilesystem
    {
        return new MultiFilesystem($filesystems, $default);
    }
}
