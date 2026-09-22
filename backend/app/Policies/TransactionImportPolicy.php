<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class TransactionImportPolicy
{
    /**
     * Imports of other users answer 404 rather than 403, so ids cannot be
     * probed to learn whether an import exists.
     */
    public function view(User $user, TransactionImport $import): Response
    {
        return $user->id === $import->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
