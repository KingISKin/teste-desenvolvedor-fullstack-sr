<?php

declare(strict_types=1);

namespace App\Domain\Transactions\Events;

/**
 * Raised when the transactions persisted by a failed import were removed.
 */
final readonly class TransactionsRolledBack
{
    public function __construct(
        public int $userId,
        public int $importId,
        public int $count,
    ) {}
}
