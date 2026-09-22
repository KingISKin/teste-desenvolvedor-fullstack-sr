<?php

declare(strict_types=1);

namespace App\Domain\Transactions\Actions;

use App\Domain\Transactions\Contracts\TransactionRepository;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListTransactions
{
    public function __construct(private TransactionRepository $transactions) {}

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function handle(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->transactions->paginateForUser($user->id, $perPage);
    }
}
