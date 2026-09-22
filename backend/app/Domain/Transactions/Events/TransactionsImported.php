<?php

declare(strict_types=1);

namespace App\Domain\Transactions\Events;

/**
 * Raised after a batch of transactions has been committed for a user.
 */
final readonly class TransactionsImported
{
    public function __construct(
        public int $userId,
        public int $importId,
        public int $count,
    ) {}
}
