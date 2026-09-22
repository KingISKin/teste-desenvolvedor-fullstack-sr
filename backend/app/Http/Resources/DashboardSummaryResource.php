<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Dashboard\DTOs\DashboardSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DashboardSummary $resource
 */
final class DashboardSummaryResource extends JsonResource
{
    /**
     * @return array{income: int, expense: int, balance: int}
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
