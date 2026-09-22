<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Imports\Enums\ImportStatus;
use Database\Factories\TransactionImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $original_filename
 * @property string $stored_path
 * @property ImportStatus $status
 * @property int|null $total_rows
 * @property int $processed_rows Rows validated and persisted.
 * @property int $failed_rows Rows rejected by validation.
 * @property int $last_processed_line Resume checkpoint (CSV line number).
 * @property list<array{line: int, message: string}>|null $errors
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
class TransactionImport extends Model
{
    /** @use HasFactory<TransactionImportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'original_filename',
        'stored_path',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'last_processed_line',
        'errors',
        'started_at',
        'finished_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'processed_rows' => 0,
        'failed_rows' => 0,
        'last_processed_line' => 0,
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
            'status' => ImportStatus::class,
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'failed_rows' => 'integer',
            'last_processed_line' => 'integer',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
