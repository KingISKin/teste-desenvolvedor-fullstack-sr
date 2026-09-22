<?php

declare(strict_types=1);

namespace App\Domain\Imports\Contracts;

/**
 * Private storage for uploaded CSV files, shared by the API and the workers.
 */
interface ImportFileStorage
{
    /**
     * Copies the file into private storage under a random name.
     *
     * @return string The stored path, used later to read or delete it.
     */
    public function store(int $userId, string $sourcePath): string;

    public function exists(string $path): bool;

    /**
     * @return resource
     */
    public function readStream(string $path);

    public function delete(string $path): void;
}
