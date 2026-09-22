<?php

declare(strict_types=1);

namespace App\Domain\Transactions\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';

    /**
     * Maps the Portuguese labels used by the CSV source ("Receita"/"Despesa").
     * Anything else is rejected (null) instead of being guessed.
     */
    public static function fromCsvLabel(string $label): ?self
    {
        return match (mb_strtolower(trim($label))) {
            'receita' => self::Income,
            'despesa' => self::Expense,
            default => null,
        };
    }
}
