<?php

declare(strict_types=1);

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\Contracts\TransactionImportRepository;
use App\Jobs\ProcessTransactionImport;
use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Accepts an uploaded CSV: stores it privately, records a pending import and
 * hands the heavy lifting to the queue so the HTTP request returns at once.
 */
final readonly class StartTransactionImport
{
    public function __construct(
        private TransactionImportRepository $imports,
        private Dispatcher $bus,
        private Config $config,
    ) {}

    public function handle(User $user, UploadedFile $file): TransactionImport
    {
        $directory = $this->config->get('imports.directory').'/'.$user->id;

        // Random server-side name: the client filename is never used as a path.
        $storedPath = $file->storeAs($directory, $file->hashName(), [
            'disk' => $this->config->get('imports.disk'),
        ]);

        if ($storedPath === false) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        $import = $this->imports->create(
            $user->id,
            mb_substr($file->getClientOriginalName(), 0, 255),
            $storedPath,
        );

        $this->bus->dispatch(new ProcessTransactionImport($import->id));

        return $import;
    }
}
