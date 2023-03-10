<?php

/*
 * This file is part of the zenstruck/filesystem package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Filesystem\Node;

use Zenstruck\Filesystem\Node;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
abstract class PlaceholderNode implements Node
{
    use DecoratedNode;

    private Path $path;

    public function __construct(?string $path = null)
    {
        if ($path) {
            $this->path = new Path($path);
        }
    }

    public function path(): Path
    {
        return $this->path ?? $this->inner()->path();
    }

    public function exists(): bool
    {
        return false;
    }
}
