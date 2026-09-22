<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Domain\Imports\Contracts\ImportProcessingQueue;
use App\Jobs\ProcessTransactionImport;
use Illuminate\Contracts\Bus\Dispatcher;

final readonly class LaravelImportProcessingQueue implements ImportProcessingQueue
{
    public function __construct(private Dispatcher $bus) {}

    public function push(int $importId): void
    {
        $this->bus->dispatch(new ProcessTransactionImport($importId));
    }
}
