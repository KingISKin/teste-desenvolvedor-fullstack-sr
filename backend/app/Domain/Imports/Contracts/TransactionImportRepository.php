<?php

declare(strict_types=1);

namespace App\Domain\Imports\Contracts;

use App\Domain\Imports\DTOs\RowError;
use App\Models\TransactionImport;

interface TransactionImportRepository
{
    public function create(int $userId, string $originalFilename, string $storedPath): TransactionImport;

    public function find(int $importId): ?TransactionImport;

    public function markProcessing(TransactionImport $import, int $totalRows): void;

    /**
     * Advances the resume checkpoint. Must run inside the same database
     * transaction as the insert of the chunk it describes.
     *
     * @param  list<RowError>  $errors
     */
    public function recordProgress(
        TransactionImport $import,
        int $importedRows,
        int $failedRows,
        array $errors,
        int $lastProcessedLine,
    ): void;

    public function markCompleted(TransactionImport $import): void;

    public function markFailed(TransactionImport $import, RowError $reason): void;
}
