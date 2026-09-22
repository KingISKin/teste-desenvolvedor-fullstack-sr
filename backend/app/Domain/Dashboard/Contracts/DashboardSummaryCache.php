<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Contracts;

use App\Domain\Dashboard\DTOs\DashboardSummary;
use Closure;

interface DashboardSummaryCache
{
    /**
     * Returns the cached summary for the user or computes and stores it.
     *
     * @param  Closure(): DashboardSummary  $compute
     */
    public function remember(int $userId, Closure $compute): DashboardSummary;

    public function forget(int $userId): void;
}
