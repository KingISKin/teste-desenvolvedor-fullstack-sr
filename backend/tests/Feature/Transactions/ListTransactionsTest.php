<?php

declare(strict_types=1);

use App\Models\Transaction;

it('lists transactions newest first with pagination metadata', function (): void {
    $user = actingAsUser();
    $older = Transaction::factory()->for($user)->create(['transaction_date' => '2026-01-01']);
    $newest = Transaction::factory()->for($user)->create(['transaction_date' => '2026-03-01']);
    $sameDayFirst = Transaction::factory()->for($user)->create(['transaction_date' => '2026-02-01']);
    $sameDaySecond = Transaction::factory()->for($user)->create(['transaction_date' => '2026-02-01']);

    $this->getJson('/api/transactions')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$newest->id, $sameDaySecond->id, $sameDayFirst->id, $older->id])
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 4)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['from', 'to', 'path']]);
});

it('exposes the transaction fields with integer cents and the type value', function (): void {
    $user = actingAsUser();
    $transaction = Transaction::factory()->for($user)->expense(433_209)->create([
        'transaction_date' => '2025-11-15',
        'description' => 'Impostos e Taxas #464',
    ]);

    $this->getJson('/api/transactions')
        ->assertOk()
        ->assertExactJson([
            'data' => [[
                'id' => $transaction->id,
                'date' => '2025-11-15',
                'description' => 'Impostos e Taxas #464',
                'amount' => 433_209,
                'type' => 'expense',
            ]],
            'links' => [
                'first' => url('/api/transactions?page=1'),
                'last' => url('/api/transactions?page=1'),
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'links' => [
                    ['url' => null, 'label' => '&laquo; Previous', 'page' => null, 'active' => false],
                    ['url' => url('/api/transactions?page=1'), 'label' => '1', 'page' => 1, 'active' => true],
                    ['url' => null, 'label' => 'Next &raquo;', 'page' => null, 'active' => false],
                ],
                'path' => url('/api/transactions'),
                'per_page' => 15,
                'to' => 1,
                'total' => 1,
            ],
        ]);
});

it('paginates with page and per_page parameters', function (): void {
    $user = actingAsUser();
    Transaction::factory()->for($user)->count(25)->create();

    $this->getJson('/api/transactions?per_page=10&page=3')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.current_page', 3)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('links.first', url('/api/transactions?per_page=10&page=1'));
});

it('only lists the authenticated user transactions', function (): void {
    $user = actingAsUser();
    $own = Transaction::factory()->for($user)->create();
    Transaction::factory()->count(3)->create();

    $this->getJson('/api/transactions')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $own->id);
});

it('bounds the page size', function (string $query): void {
    actingAsUser();

    $this->getJson("/api/transactions?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
})->with([
    'above the maximum' => ['per_page=101'],
    'zero' => ['per_page=0'],
    'not a number' => ['per_page=all'],
]);

it('accepts the maximum page size', function (): void {
    actingAsUser();

    $this->getJson('/api/transactions?per_page=100')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('rejects an invalid page number', function (): void {
    actingAsUser();

    $this->getJson('/api/transactions?page=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page']);
});
