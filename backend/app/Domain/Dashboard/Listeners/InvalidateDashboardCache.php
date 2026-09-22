<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Listeners;

use App\Domain\Dashboard\Contracts\DashboardSummaryCache;
use App\Domain\Transactions\Events\TransactionsImported;

final readonly class InvalidateDashboardCache
{
    public function __construct(private DashboardSummaryCache $cache) {}

    public function handle(TransactionsImported $event): void
    {
        $this->cache->forget($event->userId);
    }
}
