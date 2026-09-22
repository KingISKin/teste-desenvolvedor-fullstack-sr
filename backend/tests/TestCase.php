<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Points the imports disk at a directory owned by this test only.
     * Storage::fake() shares one fixed directory (and wipes it), so tests
     * running concurrently could delete each other's files.
     */
    public function fakeImportsDisk(): FilesystemAdapter
    {
        $root = storage_path('framework/testing/imports/'.Str::uuid()->toString());

        config([
            'filesystems.disks.imports-test' => ['driver' => 'local', 'root' => $root, 'throw' => false],
            'imports.disk' => 'imports-test',
        ]);

        $this->beforeApplicationDestroyed(static fn () => File::deleteDirectory($root));

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('imports-test');

        return $disk;
    }

    /**
     * Simulates a fresh HTTP request lifecycle: guards cache the resolved
     * user between calls inside one test, which would hide token revocation.
     */
    protected function forgetAuthenticatedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
