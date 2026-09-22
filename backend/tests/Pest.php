<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Authenticates a (new) user for the following requests via Sanctum.
 */
function actingAsUser(?User $user = null): User
{
    $user ??= User::factory()->create();

    Sanctum::actingAs($user);

    return $user;
}

/**
 * Builds CSV content from rows (header included by default).
 *
 * @param  list<string>  $rows
 */
function csv(array $rows, bool $withHeader = true): string
{
    $lines = $withHeader ? ['date,description,amount,type', ...$rows] : $rows;

    return implode("\n", $lines)."\n";
}

function sampleCsvPath(): string
{
    return dirname(__DIR__, 2).'/samples/financial_transactions.csv';
}
