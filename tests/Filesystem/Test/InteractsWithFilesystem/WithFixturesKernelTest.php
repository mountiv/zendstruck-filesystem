<?php

/*
 * This file is part of the zenstruck/filesystem package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Tests\Filesystem\Test\InteractsWithFilesystem;

use Zenstruck\Filesystem\Test\FixtureFilesystemProvider;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class WithFixturesKernelTest extends KernelTest implements FixtureFilesystemProvider
{
    use WithFixturesTests;
}
