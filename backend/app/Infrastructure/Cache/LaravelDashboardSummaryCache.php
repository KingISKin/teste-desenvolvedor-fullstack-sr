<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Dashboard\Contracts\DashboardSummaryCache;
use App\Domain\Dashboard\DTOs\DashboardSummary;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Per-user summary cache on the default store (Redis in Docker).
 *
 * Versioned keys: invalidation atomically increments the user's version
 * instead of deleting the entry. A summary computed from data read before
 * an invalidation is written under the old version's key, which nobody
 * reads anymore, so a stale value can never be served afterwards.
 *
 * Stampede protection: on a miss only one request per user/version runs the
 * aggregate query; concurrent requests wait briefly for its result.
 */
final readonly class LaravelDashboardSummaryCache implements DashboardSummaryCache
{
    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(
        private Cache $cache,
        private Config $config,
    ) {}

    public static function versionKey(int $userId): string
    {
        return "dashboard:version:user:{$userId}";
    }

    public static function summaryKey(int $userId, int $version): string
    {
        return "dashboard:summary:user:{$userId}:v{$version}";
    }

    public function remember(int $userId, Closure $compute): DashboardSummary
    {
        $key = self::summaryKey($userId, $this->version($userId));

        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return DashboardSummary::fromArray($cached);
        }

        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            return $this->computeAndStore($key, $compute);
        }

        try {
            return $store->lock("{$key}:lock", self::LOCK_SECONDS)->block(
                self::LOCK_WAIT_SECONDS,
                function () use ($key, $compute): DashboardSummary {
                    // Another request may have filled the entry while we waited.
                    $cached = $this->cache->get($key);

                    return is_array($cached)
                        ? DashboardSummary::fromArray($cached)
                        : $this->computeAndStore($key, $compute);
                },
            );
        } catch (LockTimeoutException) {
            // Correctness over caching: answer with fresh data, skip the write.
            return $compute();
        }
    }

    public function forget(int $userId): void
    {
        $this->cache->increment(self::versionKey($userId));
    }

    private function version(int $userId): int
    {
        return (int) $this->cache->get(self::versionKey($userId), 0);
    }

    /**
     * Only scalar arrays are stored, so cached payloads survive class refactors.
     *
     * @param  Closure(): DashboardSummary  $compute
     */
    private function computeAndStore(string $key, Closure $compute): DashboardSummary
    {
        $summary = $compute();

        $this->cache->put(
            $key,
            ['income' => $summary->income, 'expense' => $summary->expense],
            (int) $this->config->get('dashboard.cache_ttl_seconds'),
        );

        return $summary;
    }
}
