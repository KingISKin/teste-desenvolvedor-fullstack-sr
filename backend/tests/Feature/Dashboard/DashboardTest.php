<?php

declare(strict_types=1);

use App\Domain\Transactions\Events\TransactionsImported;
use App\Infrastructure\Cache\LaravelDashboardSummaryCache;
use App\Jobs\ProcessTransactionImport;
use App\Models\Transaction;
use App\Models\TransactionImport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
    $aggregateQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'from "transactions"'));

    expect($aggregateQueries)->toHaveCount(1)
        ->and(Cache::get(LaravelDashboardSummaryCache::key($user->id)))->toBe(['income' => 500, 'expense' => 0]);

    DB::flushQueryLog();
    $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.income', 500);

    expect(collect(DB::getQueryLog())->filter(
        fn (array $query): bool => str_contains($query['query'], 'from "transactions"'),
    ))->toBeEmpty();
});

it('serves the cached value until new transactions are imported', function (): void {
    Storage::fake('local');
    $user = actingAsUser();
    Transaction::factory()->for($user)->income(1_000)->create();

    $this->getJson('/api/dashboard')->assertJsonPath('data.income', 1_000);

    // A write that bypasses the import flow does not touch the cache...
    Transaction::factory()->for($user)->income(9_000)->create();
    $this->getJson('/api/dashboard')->assertJsonPath('data.income', 1_000);

    // ...while processing an import invalidates it automatically.
    $import = TransactionImport::factory()->for($user)->create(['stored_path' => 'imports/new.csv']);
    Storage::disk('local')->put($import->stored_path, csv(['2026-01-01,Salary,5000,Receita']));

    ProcessTransactionImport::dispatchSync($import->id);

    $this->getJson('/api/dashboard')->assertJsonPath('data.income', 15_000);
});

it('invalidates only the cache entry of the affected user', function (): void {
    $cache = app(LaravelDashboardSummaryCache::class);
    Cache::put($cache::key(1), ['income' => 1, 'expense' => 0]);
    Cache::put($cache::key(2), ['income' => 2, 'expense' => 0]);

    event(new TransactionsImported(userId: 1, importId: 10, count: 5));

    expect(Cache::has($cache::key(1)))->toBeFalse()
        ->and(Cache::has($cache::key(2)))->toBeTrue();
});
