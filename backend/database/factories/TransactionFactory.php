<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Transactions\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
final class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'transaction_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'description' => fake()->sentence(3),
            'amount' => fake()->numberBetween(100, 1_000_000),
            'type' => fake()->randomElement(TransactionType::cases()),
        ];
    }

    public function income(int $amount): self
    {
        return $this->state(['type' => TransactionType::Income, 'amount' => $amount]);
    }

    public function expense(int $amount): self
    {
        return $this->state(['type' => TransactionType::Expense, 'amount' => $amount]);
    }
}
