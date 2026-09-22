<?php

declare(strict_types=1);

namespace App\Domain\Transactions\DTOs;

/**
 * Sums of a user's transactions per type, in cents.
 */
final readonly class TransactionTotals
{
    public function __construct(
        public int $income,
        public int $expense,
    ) {}
}
