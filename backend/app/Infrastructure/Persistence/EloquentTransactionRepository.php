<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Transactions\Contracts\TransactionRepository;
use App\Domain\Transactions\DTOs\TransactionData;
use App\Domain\Transactions\DTOs\TransactionTotals;
use App\Domain\Transactions\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class EloquentTransactionRepository implements TransactionRepository
{
    private const LIST_COLUMNS = ['id', 'transaction_date', 'description', 'amount', 'type'];

    public function insertMany(int $userId, int $importId, array $transactions): void
    {
        if ($transactions === []) {
            return;
        }

        $now = Carbon::now();

        $rows = array_map(static fn (TransactionData $transaction): array => [
            'user_id' => $userId,
            'transaction_import_id' => $importId,
            'transaction_date' => $transaction->date->format('Y-m-d'),
            'description' => $transaction->description,
            'amount' => $transaction->amount,
            'type' => $transaction->type->value,
            'created_at' => $now,
            'updated_at' => $now,
        ], $transactions);

        // One multi-row INSERT per chunk; no per-row model events or queries.
        Transaction::query()->insert($rows);
    }

    public function deleteForImport(int $importId): int
    {
        return Transaction::query()->where('transaction_import_id', $importId)->delete();
    }

    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator
    {
        // Matches the (user_id, transaction_date, id) index: no filesort needed.
        return Transaction::query()
            ->where('user_id', $userId)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage, self::LIST_COLUMNS);
    }

    public function totalsForUser(int $userId): TransactionTotals
    {
        // Single aggregate query; each type is summed explicitly, so an
        // expense can never leak into the income total (and vice versa).
        $totals = Transaction::query()
            ->toBase()
            ->where('user_id', $userId)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS income, '
                .'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS expense',
                [TransactionType::Income->value, TransactionType::Expense->value],
            )
            ->first();

        return new TransactionTotals((int) ($totals->income ?? 0), (int) ($totals->expense ?? 0));
    }
}
