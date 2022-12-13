<?php

namespace Zenstruck\Tests\Filesystem\Node\File\Pending;

use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Zenstruck\Filesystem\Node\File\PendingFile;
use Zenstruck\Tests\Filesystem\Node\File\PendingFileTest;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class SymfonyFilePendingFileTest extends PendingFileTest
{
    protected function createPendingFile(\SplFileInfo $file, string $filename): PendingFile
    {
        return new PendingFile(new SymfonyFile($file));
    }
}
