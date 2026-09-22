<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Listeners;

use App\Domain\Dashboard\Contracts\DashboardSummaryCache;
use App\Domain\Transactions\Events\TransactionsImported;
use App\Domain\Transactions\Events\TransactionsRolledBack;

/**
 * Any change to a user's transactions makes the cached totals obsolete.
 */
final readonly class InvalidateDashboardCache
{
    public function __construct(private DashboardSummaryCache $cache) {}

    public function handle(TransactionsImported|TransactionsRolledBack $event): void
    {
        $this->cache->forget($event->userId);
    }
}
