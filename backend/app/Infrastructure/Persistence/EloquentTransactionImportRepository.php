<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Imports\Contracts\TransactionImportRepository;
use App\Domain\Imports\DTOs\RowError;
use App\Domain\Imports\Enums\ImportStatus;
use App\Models\TransactionImport;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;

final readonly class EloquentTransactionImportRepository implements TransactionImportRepository
{
    public function __construct(private Config $config) {}

    public function create(int $userId, string $originalFilename, string $storedPath): TransactionImport
    {
        return TransactionImport::query()->create([
            'user_id' => $userId,
            'original_filename' => $originalFilename,
            'stored_path' => $storedPath,
            'status' => ImportStatus::Pending,
        ]);
    }

    public function find(int $importId): ?TransactionImport
    {
        return TransactionImport::query()->find($importId);
    }

    public function markProcessing(TransactionImport $import, int $totalRows): void
    {
        $import->forceFill([
            'status' => ImportStatus::Processing,
            'total_rows' => $totalRows,
            'started_at' => $import->started_at ?? Carbon::now(),
        ])->save();
    }

    public function recordProgress(
        TransactionImport $import,
        int $importedRows,
        int $failedRows,
        array $errors,
        int $lastProcessedLine,
    ): void {
        $import->forceFill([
            'processed_rows' => $import->processed_rows + $importedRows,
            'failed_rows' => $import->failed_rows + $failedRows,
            'errors' => $this->appendErrors($import->errors ?? [], $errors),
            'last_processed_line' => $lastProcessedLine,
        ])->save();
    }

    public function markCompleted(TransactionImport $import): void
    {
        $import->forceFill([
            'status' => ImportStatus::Completed,
            'finished_at' => Carbon::now(),
        ])->save();
    }

    public function markFailed(TransactionImport $import, RowError $reason): void
    {
        $import->forceFill([
            'status' => ImportStatus::Failed,
            'errors' => $this->appendErrors([$reason->toArray()], $import->errors ?? []),
            'finished_at' => Carbon::now(),
        ])->save();
    }

    public function markRolledBack(TransactionImport $import, RowError $reason): void
    {
        $import->forceFill([
            'status' => ImportStatus::Failed,
            'processed_rows' => 0,
            'failed_rows' => 0,
            'last_processed_line' => 0,
            // Row errors described rows that were never kept: only the reason remains.
            'errors' => [$reason->toArray()],
            'finished_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Stored errors are capped so a badly broken file cannot bloat the row.
     *
     * @param  list<array{line: int, message: string}>  $stored
     * @param  list<RowError|array{line: int, message: string}>  $new
     * @return list<array{line: int, message: string}>
     */
    private function appendErrors(array $stored, array $new): array
    {
        $limit = (int) $this->config->get('imports.max_stored_errors');

        foreach ($new as $error) {
            if (count($stored) >= $limit) {
                break;
            }

            $stored[] = $error instanceof RowError ? $error->toArray() : $error;
        }

        return $stored;
    }
}
