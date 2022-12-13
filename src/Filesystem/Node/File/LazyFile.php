<?php

namespace Zenstruck\Filesystem\Node\File;

use Zenstruck\Filesystem;
use Zenstruck\Filesystem\Node\DecoratedNode;
use Zenstruck\Filesystem\Node\File;
use Zenstruck\Filesystem\Node\Path;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class LazyFile implements File
{
    use DecoratedFile, DecoratedNode;

    private ?Filesystem $filesystem = null;
    private File $inner;
    private Path $pathObject;

    public function __construct(private string $path)
    {
    }

    public function setFilesystem(Filesystem $filesystem): void
    {
        $this->filesystem = $filesystem;
    }

    public function path(): Path
    {
        return $this->pathObject ??= new Path($this->path);
    }

    public function guessExtension(): ?string
    {
        return $this->path()->extension() ?? $this->inner()->guessExtension();
    }

    protected function inner(): File
    {
        return $this->inner ??= $this->filesystem()->file($this->path());
    }

    protected function filesystem(): Filesystem
    {
        return $this->filesystem ?? throw new \RuntimeException('Filesystem not set.');
    }
}
