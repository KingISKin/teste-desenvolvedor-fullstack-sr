<?php

declare(strict_types=1);

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\Contracts\TransactionImportRepository;
use App\Domain\Imports\DTOs\RowError;

/**
 * Marks an import as failed after its job exhausted every attempt. The
 * internal exception is logged by the queue, never exposed to the client.
 */
final readonly class FailTransactionImport
{
    public function __construct(private TransactionImportRepository $imports) {}

    public function handle(int $importId): void
    {
        $import = $this->imports->find($importId);

        if ($import === null || $import->status->isTerminal()) {
            return;
        }

        $this->imports->markFailed(
            $import,
            new RowError(0, 'The import could not be processed. Please try uploading the file again.'),
        );
    }
}
