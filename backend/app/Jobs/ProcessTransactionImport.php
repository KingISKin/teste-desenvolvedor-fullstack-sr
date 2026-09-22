<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Imports\Actions\FailTransactionImport;
use App\Domain\Imports\Actions\ImportTransactionsFromCsv;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Thin queue adapter: retry policy lives here, business logic in the action.
 * Only the import id is serialized, never the model or file contents.
 */
final class ProcessTransactionImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Seconds to wait before each retry. */
    public array $backoff = [10, 30, 60];

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $importId) {}

    /**
     * Defense in depth: a duplicate delivery of the same import (e.g. after a
     * worker crash) is dropped while another worker still holds the lock. The
     * lock expires with the job timeout, i.e. before the queue retry_after
     * (660s), so a crashed worker can never leave an import stuck.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("transaction-import:{$this->importId}"))
                ->dontRelease()
                ->expireAfter($this->timeout),
        ];
    }

    public function handle(ImportTransactionsFromCsv $importTransactions): void
    {
        $importTransactions->handle($this->importId);
    }

    public function failed(?Throwable $exception): void
    {
        app(FailTransactionImport::class)->handle($this->importId);
    }
}
