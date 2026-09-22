<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Transactions\Contracts\TransactionRepository;
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
    ];
}
