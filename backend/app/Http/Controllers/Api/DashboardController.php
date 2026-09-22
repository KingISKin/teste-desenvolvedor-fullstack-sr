<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Dashboard\Actions\GetDashboardSummary;
use App\Http\Resources\DashboardSummaryResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

final class DashboardController
{
    public function __invoke(#[CurrentUser] User $user, GetDashboardSummary $getSummary): DashboardSummaryResource
    {
        return new DashboardSummaryResource($getSummary->handle($user));
    }
}
