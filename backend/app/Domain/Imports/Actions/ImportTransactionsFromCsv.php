<?php

declare(strict_types=1);

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\Contracts\CsvReader;
use App\Domain\Imports\Contracts\TransactionImportRepository;
use App\Domain\Imports\Csv\TransactionCsvRowParser;
use App\Domain\Imports\DTOs\RowError;
use App\Domain\Imports\Exceptions\InvalidCsvHeader;
use App\Domain\Imports\Exceptions\InvalidTransactionRow;
use App\Domain\Transactions\Contracts\TransactionRepository;
use App\Domain\Transactions\DTOs\TransactionData;
use App\Domain\Transactions\Events\TransactionsImported;
use App\Models\TransactionImport;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Contracts\Filesystem\Filesystem as Disk;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Streams a stored CSV, validates every row and bulk inserts the valid ones.
 *
 * Idempotency: each chunk insert and the import checkpoint (last processed
 * line and counters) are committed in the same database transaction. A retry
 * resumes after the checkpoint, so rows are never inserted twice.
 */
final class ImportTransactionsFromCsv
{
    /** @var list<TransactionData> */
    private array $pendingTransactions = [];

    /** @var list<RowError> */
    private array $pendingErrors = [];

    private int $pendingLines = 0;

    public function __construct(
        private readonly TransactionImportRepository $imports,
        private readonly TransactionRepository $transactions,
        private readonly TransactionCsvRowParser $parser,
        private readonly CsvReader $reader,
        private readonly ConnectionInterface $database,
        private readonly Events $events,
        private readonly Filesystem $filesystem,
        private readonly Config $config,
    ) {}

    public function handle(int $importId): void
    {
        $import = $this->imports->find($importId);

        // Unknown or already finished imports are ignored (safe redelivery).
        if ($import === null || $import->status->isTerminal()) {
            return;
        }

        $disk = $this->disk();

        if (! $disk->exists($import->stored_path)) {
            $this->imports->markFailed($import, new RowError(0, 'The uploaded file is no longer available.'));

            return;
        }

        try {
            $totalRows = $this->countDataRows($disk, $import->stored_path);
        } catch (InvalidCsvHeader $exception) {
            $this->imports->markFailed($import, new RowError(1, $exception->getMessage()));

            return;
        }

        $this->imports->markProcessing($import, $totalRows);
        $this->processRows($import, $disk);
        $this->imports->markCompleted($import);

        // The data now lives in the database; the raw upload is no longer needed.
        // Failed imports keep their file for troubleshooting.
        $disk->delete($import->stored_path);
    }

    private function processRows(TransactionImport $import, Disk $disk): void
    {
        $chunkSize = max(1, (int) $this->config->get('imports.chunk_size'));
        $checkpoint = $import->last_processed_line;
        $lastLine = $checkpoint;

        $this->resetPending();

        foreach ($this->dataRecords($disk, $import->stored_path) as $line => $fields) {
            if ($line <= $checkpoint) {
                continue;
            }

            try {
                $this->pendingTransactions[] = $this->parser->parse($fields);
            } catch (InvalidTransactionRow $exception) {
                $this->pendingErrors[] = new RowError($line, $exception->getMessage());
            }

            $lastLine = $line;
            $this->pendingLines++;

            if ($this->pendingLines >= $chunkSize) {
                $this->flush($import, $lastLine);
            }
        }

        if ($this->pendingLines > 0) {
            $this->flush($import, $lastLine);
        }
    }

    private function flush(TransactionImport $import, int $lastLine): void
    {
        $transactions = $this->pendingTransactions;
        $errors = $this->pendingErrors;

        $this->database->transaction(function () use ($import, $transactions, $errors, $lastLine): void {
            if ($transactions !== []) {
                $this->transactions->insertMany($import->user_id, $import->id, $transactions);
            }

            $this->imports->recordProgress($import, count($transactions), count($errors), $errors, $lastLine);
        });

        if ($transactions !== []) {
            $this->events->dispatch(new TransactionsImported($import->user_id, $import->id, count($transactions)));
        }

        $this->resetPending();
    }

    private function resetPending(): void
    {
        $this->pendingTransactions = [];
        $this->pendingErrors = [];
        $this->pendingLines = 0;
    }

    /**
     * Validates the header and counts data rows in a cheap streaming pass, so
     * clients can display real progress while the import runs.
     *
     * @throws InvalidCsvHeader
     */
    private function countDataRows(Disk $disk, string $path): int
    {
        $count = 0;

        foreach ($this->dataRecords($disk, $path) as $ignored) {
            $count++;
        }

        return $count;
    }

    /**
     * @return iterable<int, list<string|null>>
     *
     * @throws InvalidCsvHeader
     */
    private function dataRecords(Disk $disk, string $path): iterable
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to open import file [{$path}].");
        }

        try {
            $headerSeen = false;

            foreach ($this->reader->records($stream) as $line => $fields) {
                if (! $headerSeen) {
                    $this->parser->assertValidHeader($fields);
                    $headerSeen = true;

                    continue;
                }

                yield $line => $fields;
            }

            if (! $headerSeen) {
                throw InvalidCsvHeader::expected(TransactionCsvRowParser::HEADER);
            }
        } finally {
            fclose($stream);
        }
    }

    private function disk(): Disk
    {
        return $this->filesystem->disk((string) $this->config->get('imports.disk'));
    }
}
