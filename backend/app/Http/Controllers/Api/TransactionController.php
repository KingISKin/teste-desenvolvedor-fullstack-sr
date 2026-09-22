<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Transactions\Actions\ListTransactions;
use App\Http\Requests\ListTransactionsRequest;
use App\Http\Resources\TransactionResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TransactionController
{
    public function index(
        ListTransactionsRequest $request,
        #[CurrentUser] User $user,
        ListTransactions $listTransactions,
    ): AnonymousResourceCollection {
        return TransactionResource::collection(
            $listTransactions->handle($user, $request->perPage())->withQueryString(),
        );
    }
}
