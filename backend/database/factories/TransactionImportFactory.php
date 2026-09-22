<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Imports\Enums\ImportStatus;
use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionImport>
 */
final class TransactionImportFactory extends Factory
{
    protected $model = TransactionImport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => 'transactions.csv',
            'stored_path' => 'imports/'.fake()->uuid().'.csv',
            'status' => ImportStatus::Pending,
        ];
    }
}
