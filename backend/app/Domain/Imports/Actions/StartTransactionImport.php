<?php

declare(strict_types=1);

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\Contracts\ImportFileStorage;
use App\Domain\Imports\Contracts\ImportProcessingQueue;
use App\Domain\Imports\Contracts\TransactionImportRepository;
use App\Domain\Imports\DTOs\RowError;
use App\Domain\Imports\DTOs\UploadedCsv;
use App\Models\TransactionImport;
use App\Models\User;
use Throwable;

/**
 * Accepts an uploaded CSV: stores it privately, records a pending import and
 * hands the heavy lifting to the queue so the HTTP request returns at once.
 */
final readonly class StartTransactionImport
{
    public function __construct(
        private TransactionImportRepository $imports,
        private ImportFileStorage $files,
        private ImportProcessingQueue $queue,
    ) {}

    /**
     * @throws Throwable When the import cannot be queued (it is marked as failed first).
     */
    public function handle(User $user, UploadedCsv $upload): TransactionImport
    {
        $storedPath = $this->files->store($user->id, $upload->temporaryPath);

        $import = $this->imports->create(
            $user->id,
            mb_substr($upload->originalName, 0, 255),
            $storedPath,
        );

        try {
            $this->queue->push($import->id);
        } catch (Throwable $exception) {
            // Never leave a "pending" import that no worker will ever pick up.
            $this->files->delete($storedPath);
            $this->imports->markFailed($import, new RowError(0, 'The import could not be queued. Please try again.'));

            throw $exception;
        }

        return $import;
    }
}
