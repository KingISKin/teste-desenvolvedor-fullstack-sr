<?php

declare(strict_types=1);

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\Contracts\TransactionImportRepository;
use App\Domain\Imports\DTOs\RowError;
use App\Domain\Transactions\Contracts\TransactionRepository;
use App\Domain\Transactions\Events\TransactionsRolledBack;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Database\ConnectionInterface;

/**
 * Runs after the import job exhausted every attempt. Chunks committed by
 * earlier attempts are removed so an import is all-or-nothing: users never
 * see partial totals, and uploading the file again cannot duplicate rows.
 * The internal exception is logged by the queue, never exposed to clients.
 */
final readonly class FailTransactionImport
{
    public function __construct(
        private TransactionImportRepository $imports,
        private TransactionRepository $transactions,
        private ConnectionInterface $database,
        private Events $events,
    ) {}

    public function handle(int $importId): void
    {
        $import = $this->imports->find($importId);

        if ($import === null || $import->status->isTerminal()) {
            return;
        }

        $removed = $this->database->transaction(function () use ($import): int {
            $removed = $this->transactions->deleteForImport($import->id);

            $this->imports->markRolledBack(
                $import,
                new RowError(0, 'The import could not be processed and no rows were saved. Please upload the file again.'),
            );

            return $removed;
        });

        if ($removed > 0) {
            $this->events->dispatch(new TransactionsRolledBack($import->user_id, $import->id, $removed));
        }
    }
}
