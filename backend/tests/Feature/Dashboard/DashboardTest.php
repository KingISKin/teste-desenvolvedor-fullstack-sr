<?php

declare(strict_types=1);

use App\Domain\Dashboard\Contracts\DashboardSummaryCache;
use App\Domain\Dashboard\DTOs\DashboardSummary;
use App\Domain\Transactions\Events\TransactionsImported;
use App\Infrastructure\Cache\LaravelDashboardSummaryCache;
use App\Jobs\ProcessTransactionImport;
use App\Models\Transaction;
use App\Models\TransactionImport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('sums income and expense separately and derives the balance', function (): void {
    $user = actingAsUser();
    Transaction::factory()->for($user)->income(100_000)->create();
    Transaction::factory()->for($user)->income(50_050)->create();
    Transaction::factory()->for($user)->expense(30_000)->create();
    Transaction::factory()->for($user)->expense(20_025)->create();

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertExactJson(['data' => [
            'income' => 150_050,
            'expense' => 50_025,
            'balance' => 100_025,
        ]]);
});

it('never counts expenses as income', function (): void {
    $user = actingAsUser();
    Transaction::factory()->for($user)->expense(40_000)->count(3)->create();

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertExactJson(['data' => [
            'income' => 0,
            'expense' => 120_000,
            'balance' => -120_000,
        ]]);
});

it('returns zeros for a user without transactions', function (): void {
    actingAsUser();

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertExactJson(['data' => ['income' => 0, 'expense' => 0, 'balance' => 0]]);
});

it('only aggregates the authenticated user transactions', function (): void {
    $user = actingAsUser();
    Transaction::factory()->for($user)->income(1_000)->create();
    Transaction::factory()->income(999_999)->create();
    Transaction::factory()->expense(888_888)->create();

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertExactJson(['data' => ['income' => 1_000, 'expense' => 0, 'balance' => 1_000]]);
});

it('computes the summary with a single aggregate query and caches it per user', function (): void {
    $user = actingAsUser();
    Transaction::factory()->for($user)->income(500)->create();

    DB::enableQueryLog();
    $this->getJson('/api/dashboard')->assertOk();
    $aggregateQueries = transactionQueries();

    expect($aggregateQueries)->toHaveCount(1)
        ->and(Cache::get(LaravelDashboardSummaryCache::summaryKey($user->id, 0)))->toBe(['income' => 500, 'expense' => 0]);

    DB::flushQueryLog();
    $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.income', 500);

    expect(transactionQueries())->toBeEmpty();
});

it('serves the cached value until new transactions are imported', function (): void {
    $disk = $this->fakeImportsDisk();
    $user = actingAsUser();
    Transaction::factory()->for($user)->income(1_000)->create();

    $this->getJson('/api/dashboard')->assertJsonPath('data.income', 1_000);

    // A write that bypasses the import flow does not touch the cache...
    Transaction::factory()->for($user)->income(9_000)->create();
    $this->getJson('/api/dashboard')->assertJsonPath('data.income', 1_000);

    // ...while processing an import invalidates it automatically.
    $import = TransactionImport::factory()->for($user)->create();
    $disk->put($import->stored_path, csv(['2026-01-01,Salary,5000,Receita']));

    ProcessTransactionImport::dispatchSync($import->id);

    $this->getJson('/api/dashboard')->assertJsonPath('data.income', 15_000);
});

it('invalidates only the cache of the affected user', function (): void {
    $cache = app(DashboardSummaryCache::class);
    $cache->remember(1, fn (): DashboardSummary => new DashboardSummary(1, 0));
    $cache->remember(2, fn (): DashboardSummary => new DashboardSummary(2, 0));

    event(new TransactionsImported(userId: 1, importId: 10, count: 5));

    expect($cache->remember(1, fn (): DashboardSummary => new DashboardSummary(100, 0))->income)->toBe(100)
        ->and($cache->remember(2, fn (): DashboardSummary => new DashboardSummary(200, 0))->income)->toBe(2);
});

it('never serves a summary computed before an invalidation (read/invalidate race)', function (): void {
    $cache = app(DashboardSummaryCache::class);

    // A request reads the old totals; while it computes, an import commits
    // new transactions and invalidates the cache; then it writes its result.
    $stale = $cache->remember(1, function () use ($cache): DashboardSummary {
        $staleSummary = new DashboardSummary(income: 100, expense: 0);
        $cache->forget(1);

        return $staleSummary;
    });

    $next = $cache->remember(1, fn (): DashboardSummary => new DashboardSummary(income: 900, expense: 0));

    expect($stale->income)->toBe(100)
        ->and($next->income)->toBe(900);
});

it('computes a missing summary only once for concurrent readers of the same version', function (): void {
    $cache = app(DashboardSummaryCache::class);
    $computations = 0;
    $compute = function () use (&$computations): DashboardSummary {
        $computations++;

        return new DashboardSummary(income: 5, expense: 1);
    };

    $cache->remember(7, $compute);
    $cache->remember(7, $compute);

    expect($computations)->toBe(1);
});

/**
 * Queries that touched the transactions table, whatever the SQL quoting style
 * of the connection (`transactions`, "transactions" or bare).
 *
 * @return Collection<int, array{query: string}>
 */
function transactionQueries(): Collection
{
    return collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => preg_match('/\bfrom\s+[`"]?transactions[`"]?/i', $query['query']) === 1);
}
