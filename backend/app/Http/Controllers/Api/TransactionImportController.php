<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Imports\Actions\StartTransactionImport;
use App\Http\Requests\StoreTransactionImportRequest;
use App\Http\Resources\TransactionImportResource;
use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class TransactionImportController
{
    /**
     * 202 Accepted: the file is queued; clients poll the returned import.
     */
    public function store(
        StoreTransactionImportRequest $request,
        #[CurrentUser] User $user,
        StartTransactionImport $startImport,
    ): JsonResponse {
        $import = $startImport->handle($user, $request->toUploadedCsv());

        return (new TransactionImportResource($import))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED)
            ->header('Location', route('imports.show', $import));
    }

    /**
     * Authorization is enforced by the route's "can:view,import" middleware.
     */
    public function show(TransactionImport $import): TransactionImportResource
    {
        return new TransactionImportResource($import);
    }
}
