<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Imports\Contracts\ImportFileStorage;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Contracts\Filesystem\Filesystem as Disk;
use Illuminate\Http\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores uploads on a private Laravel disk (never served over HTTP). In
 * Docker the disk lives on a volume shared by the app and worker containers.
 */
final readonly class LaravelImportFileStorage implements ImportFileStorage
{
    public function __construct(
        private Filesystem $filesystem,
        private Config $config,
    ) {}

    public function store(int $userId, string $sourcePath): string
    {
        $directory = $this->config->get('imports.directory').'/'.$userId;

        // Random server-side name: the client filename is never used as a path.
        $path = $this->disk()->putFileAs($directory, new File($sourcePath), Str::uuid()->toString().'.csv');

        if (! is_string($path)) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        return $path;
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function readStream(string $path)
    {
        $stream = $this->disk()->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to open import file [{$path}].");
        }

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    private function disk(): Disk
    {
        return $this->filesystem->disk((string) $this->config->get('imports.disk'));
    }
}
