<?php

declare(strict_types=1);

use App\Domain\Imports\Csv\TransactionCsvRowParser;
use App\Domain\Imports\Exceptions\InvalidCsvHeader;
use App\Domain\Imports\Exceptions\InvalidTransactionRow;
use App\Domain\Transactions\Enums\TransactionType;

beforeEach(function (): void {
    $this->parser = new TransactionCsvRowParser;
});

it('parses a valid income row', function (): void {
    $transaction = $this->parser->parse(['2026-05-01', 'Mensalidade Cliente B #869', '115346', 'Receita']);

    expect($transaction->date->format('Y-m-d'))->toBe('2026-05-01')
        ->and($transaction->description)->toBe('Mensalidade Cliente B #869')
        ->and($transaction->amount)->toBe(115346)
        ->and($transaction->type)->toBe(TransactionType::Income);
});

it('parses a valid expense row and trims surrounding whitespace', function (): void {
    $transaction = $this->parser->parse([' 2025-11-15 ', ' Impostos e Taxas ', ' 433209 ', ' Despesa ']);

    expect($transaction->description)->toBe('Impostos e Taxas')
        ->and($transaction->amount)->toBe(433209)
        ->and($transaction->type)->toBe(TransactionType::Expense);
});

it('keeps multibyte descriptions intact', function (): void {
    $transaction = $this->parser->parse(['2026-01-10', 'Serviços Prestados', '100', 'Receita']);

    expect($transaction->description)->toBe('Serviços Prestados');
});

it('rejects invalid rows with a descriptive message', function (array $fields, string $message): void {
    expect(fn () => $this->parser->parse($fields))
        ->toThrow(InvalidTransactionRow::class, $message);
})->with([
    'impossible date' => [['2026-02-30', 'X', '100', 'Receita'], 'Date must be a valid calendar date'],
    'wrong date format' => [['01/05/2026', 'X', '100', 'Receita'], 'Date must be a valid calendar date'],
    'empty date' => [['', 'X', '100', 'Receita'], 'Date must be a valid calendar date'],
    'empty description' => [['2026-01-01', '   ', '100', 'Receita'], 'Description is required.'],
    'too long description' => [['2026-01-01', str_repeat('a', 256), '100', 'Receita'], 'must not exceed 255 characters'],
    'invalid utf-8' => [['2026-01-01', "\xC3\x28", '100', 'Receita'], 'valid UTF-8'],
    'decimal amount' => [['2026-01-01', 'X', '10.50', 'Receita'], 'Amount must be a positive integer'],
    'negative amount' => [['2026-01-01', 'X', '-100', 'Despesa'], 'Amount must be a positive integer'],
    'zero amount' => [['2026-01-01', 'X', '0', 'Despesa'], 'Amount must be a positive integer'],
    'non numeric amount' => [['2026-01-01', 'X', 'abc', 'Despesa'], 'Amount must be a positive integer'],
    'overflowing amount' => [['2026-01-01', 'X', '9999999999999999999', 'Despesa'], 'Amount must be a positive integer'],
    'unknown type' => [['2026-01-01', 'X', '100', 'Transfer'], 'Type must be "Receita" or "Despesa".'],
    'too few columns' => [['2026-01-01', 'X', '100'], 'Expected 4 columns, got 3.'],
    'too many columns' => [['2026-01-01', 'X', '100', 'Receita', 'extra'], 'Expected 4 columns, got 5.'],
]);

it('reports every violation of a row at once', function (): void {
    expect(fn () => $this->parser->parse(['bad', '', 'x', 'y']))
        ->toThrow(function (InvalidTransactionRow $exception): void {
            expect($exception->getMessage())
                ->toContain('Date')
                ->toContain('Description')
                ->toContain('Amount')
                ->toContain('Type');
        });
});

it('accepts the exact header, case-insensitively and with a UTF-8 BOM', function (array $header): void {
    $this->parser->assertValidHeader($header);

    expect(true)->toBeTrue();
})->with([
    'exact' => [['date', 'description', 'amount', 'type']],
    'upper case and spaces' => [[' Date', 'DESCRIPTION ', 'Amount', 'Type']],
    'with BOM' => [["\u{FEFF}date", 'description', 'amount', 'type']],
]);

it('rejects any other header', function (array $header): void {
    expect(fn () => $this->parser->assertValidHeader($header))
        ->toThrow(InvalidCsvHeader::class, 'Expected exactly: date,description,amount,type.');
})->with([
    'reordered' => [['description', 'date', 'amount', 'type']],
    'missing column' => [['date', 'description', 'amount']],
    'extra column' => [['date', 'description', 'amount', 'type', 'category']],
    'data row as header' => [['2026-05-01', 'Mensalidade', '115346', 'Receita']],
]);
