<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Dashboard\Contracts\DashboardSummaryCache;
use App\Domain\Dashboard\DTOs\DashboardSummary;
use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Per-user cache entry on the default store (Redis in Docker). Only scalar
 * arrays are stored, so cached payloads survive class refactors.
 */
final readonly class LaravelDashboardSummaryCache implements DashboardSummaryCache
{
    public function __construct(
        private Cache $cache,
        private Config $config,
    ) {}

    public static function key(int $userId): string
    {
        return "dashboard:summary:user:{$userId}";
    }

    public function remember(int $userId, Closure $compute): DashboardSummary
    {
        /** @var array{income: int, expense: int} $totals */
        $totals = $this->cache->remember(
            self::key($userId),
            (int) $this->config->get('dashboard.cache_ttl_seconds'),
            static function () use ($compute): array {
                $summary = $compute();

                return ['income' => $summary->income, 'expense' => $summary->expense];
            },
        );

        return DashboardSummary::fromArray($totals);
    }

    public function forget(int $userId): void
    {
        $this->cache->forget(self::key($userId));
    }
}
