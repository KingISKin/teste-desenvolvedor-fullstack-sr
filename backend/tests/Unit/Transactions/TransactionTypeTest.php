<?php

declare(strict_types=1);

use App\Domain\Transactions\Enums\TransactionType;

it('maps CSV labels to transaction types', function (string $label, TransactionType $expected): void {
    expect(TransactionType::fromCsvLabel($label))->toBe($expected);
})->with([
    'Receita' => ['Receita', TransactionType::Income],
    'Despesa' => ['Despesa', TransactionType::Expense],
    'lower case' => ['receita', TransactionType::Income],
    'upper case with spaces' => ['  DESPESA ', TransactionType::Expense],
]);

it('rejects unknown labels instead of guessing', function (string $label): void {
    expect(TransactionType::fromCsvLabel($label))->toBeNull();
})->with(['', 'Income', 'expense', 'Receitas', 'Transferência']);

it('persists stable backed values', function (): void {
    expect(TransactionType::Income->value)->toBe('income')
        ->and(TransactionType::Expense->value)->toBe('expense');
});
