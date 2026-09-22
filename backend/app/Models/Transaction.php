<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Transactions\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $transaction_import_id
 * @property Carbon $transaction_date
 * @property string $description
 * @property int $amount Amount in cents (always positive; the sign comes from $type).
 * @property TransactionType $type
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'transaction_import_id',
        'transaction_date',
        'description',
        'amount',
        'type',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'integer',
            'type' => TransactionType::class,
        ];
    }
}
