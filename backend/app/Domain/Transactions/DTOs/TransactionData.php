<?php

declare(strict_types=1);

namespace App\Domain\Transactions\DTOs;

use App\Domain\Transactions\Enums\TransactionType;
use DateTimeImmutable;

final readonly class TransactionData
{
    /**
     * @param  int  $amount  Positive amount in cents; the sign is expressed by $type.
     */
    public function __construct(
        public DateTimeImmutable $date,
        public string $description,
        public int $amount,
        public TransactionType $type,
    ) {}
}
