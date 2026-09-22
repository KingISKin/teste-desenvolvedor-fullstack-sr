<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

/**
 * Dashboard totals in cents. Balance is always derived (income - expense),
 * so an expense can never be counted as income.
 */
final readonly class DashboardSummary
{
    public int $balance;

    public function __construct(
        public int $income,
        public int $expense,
    ) {
        $this->balance = $income - $expense;
    }

    /**
     * @param  array{income: int, expense: int}  $totals
     */
    public static function fromArray(array $totals): self
    {
        return new self($totals['income'], $totals['expense']);
    }

    /**
     * @return array{income: int, expense: int, balance: int}
     */
    public function toArray(): array
    {
        return [
            'income' => $this->income,
            'expense' => $this->expense,
            'balance' => $this->balance,
        ];
    }
}
