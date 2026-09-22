<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Actions;

use App\Domain\Dashboard\Contracts\DashboardSummaryCache;
use App\Domain\Dashboard\DTOs\DashboardSummary;
use App\Domain\Transactions\Contracts\TransactionRepository;
use App\Models\User;

final readonly class GetDashboardSummary
{
    public function __construct(
        private TransactionRepository $transactions,
        private DashboardSummaryCache $cache,
    ) {}

    public function handle(User $user): DashboardSummary
    {
        return $this->cache->remember(
            $user->id,
            function () use ($user): DashboardSummary {
                $totals = $this->transactions->totalsForUser($user->id);

                return new DashboardSummary($totals->income, $totals->expense);
            },
        );
    }
}
