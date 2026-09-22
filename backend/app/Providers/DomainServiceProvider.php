<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Imports\Contracts\CsvReader;
use App\Domain\Imports\Contracts\TransactionImportRepository;
use App\Domain\Transactions\Contracts\TransactionRepository;
use App\Infrastructure\Csv\StreamingCsvReader;
use App\Infrastructure\Persistence\EloquentTransactionImportRepository;
use App\Infrastructure\Persistence\EloquentTransactionRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires domain contracts to their infrastructure implementations.
 */
final class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        TransactionRepository::class => EloquentTransactionRepository::class,
        TransactionImportRepository::class => EloquentTransactionImportRepository::class,
        CsvReader::class => StreamingCsvReader::class,
    ];
}
