<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
final class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->transaction_date->toDateString(),
            'description' => $this->description,
            'amount' => $this->amount,
            'type' => $this->type->value,
        ];
    }
}
