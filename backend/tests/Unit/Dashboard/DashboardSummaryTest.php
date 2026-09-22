<?php

declare(strict_types=1);

use App\Domain\Dashboard\DTOs\DashboardSummary;

it('derives the balance as income minus expense in integer cents', function (): void {
    $summary = new DashboardSummary(income: 115_346, expense: 433_209);

    expect($summary->balance)->toBe(-317_863)
        ->and($summary->toArray())->toBe([
            'income' => 115_346,
            'expense' => 433_209,
            'balance' => -317_863,
        ]);
});

it('keeps integer precision for large totals (no floating point drift)', function (): void {
    $summary = new DashboardSummary(income: 3_611_960_974, expense: 2_670_954_574);

    expect($summary->balance)->toBe(941_006_400)->toBeInt();
});

it('round-trips through its array representation', function (): void {
    $summary = DashboardSummary::fromArray(['income' => 10, 'expense' => 4]);

    expect($summary->toArray())->toBe(['income' => 10, 'expense' => 4, 'balance' => 6]);
});
