<?php

declare(strict_types=1);

namespace App\Domain\Transactions\Contracts;

use App\Domain\Transactions\DTOs\TransactionData;
use App\Domain\Transactions\DTOs\TransactionTotals;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TransactionRepository
{
    /**
     * Bulk inserts the given transactions in a single statement.
     *
     * @param  list<TransactionData>  $transactions
     */
    public function insertMany(int $userId, int $importId, array $transactions): void;

    /**
     * Removes every transaction persisted by the given import.
     *
     * @return int Number of deleted rows.
     */
    public function deleteForImport(int $importId): int;

    /**
     * Newest first (date desc, id desc), scoped to the user.
     *
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator;

    /**
     * Income and expense totals for the user computed by one aggregate query.
     */
    public function totalsForUser(int $userId): TransactionTotals;
}
