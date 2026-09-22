<?php

declare(strict_types=1);

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\Contracts\CsvReader;
use App\Domain\Imports\Contracts\ImportFileStorage;
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
use Illuminate\Database\ConnectionInterface;

/**
 * Streams a stored CSV, validates every row and bulk inserts the valid ones.
 *
 * Idempotency: each chunk insert and the import checkpoint (last processed
 * line and counters) are committed in the same database transaction. A retry
 * resumes after the checkpoint, so rows are never inserted twice.
 */
final readonly class ImportTransactionsFromCsv
{
    public function __construct(
        private TransactionImportRepository $imports,
        private TransactionRepository $transactions,
        private TransactionCsvRowParser $parser,
        private CsvReader $reader,
        private ImportFileStorage $files,
        private ConnectionInterface $database,
        private Events $events,
        private Config $config,
    ) {}

    public function handle(int $importId): void
    {
        $import = $this->imports->find($importId);

        // Unknown or already finished imports are ignored (safe redelivery).
        if ($import === null || $import->status->isTerminal()) {
            return;
        }

        if (! $this->files->exists($import->stored_path)) {
            $this->imports->markFailed(
                $import,
                new RowError(0, 'The uploaded file could not be found on the server. Please upload it again.'),
            );

            return;
        }

        try {
            $totalRows = $this->countDataRows($import->stored_path);
        } catch (InvalidCsvHeader $exception) {
            $this->imports->markFailed($import, new RowError(1, $exception->getMessage()));

            return;
        }

        $this->imports->markProcessing($import, $totalRows);
        $this->processRows($import);
        $this->imports->markCompleted($import);

        // The data now lives in the database; the raw upload is no longer needed.
        // Failed imports keep their file for troubleshooting.
        $this->files->delete($import->stored_path);
    }

    private function processRows(TransactionImport $import): void
    {
        $chunkSize = max(1, (int) $this->config->get('imports.chunk_size'));
        $checkpoint = $import->last_processed_line;

        /** @var list<TransactionData> $transactions */
        $transactions = [];
        /** @var list<RowError> $errors */
        $errors = [];
        $pendingLines = 0;
        $lastLine = $checkpoint;

        foreach ($this->dataRecords($import->stored_path) as $line => $fields) {
            if ($line <= $checkpoint) {
                continue;
            }

            try {
                $transactions[] = $this->parser->parse($fields);
            } catch (InvalidTransactionRow $exception) {
                $errors[] = new RowError($line, $exception->getMessage());
            }

            $lastLine = $line;

            if (++$pendingLines >= $chunkSize) {
                $this->commitChunk($import, $transactions, $errors, $lastLine);
                [$transactions, $errors, $pendingLines] = [[], [], 0];
            }
        }

        if ($pendingLines > 0) {
            $this->commitChunk($import, $transactions, $errors, $lastLine);
        }
    }

    /**
     * @param  list<TransactionData>  $transactions
     * @param  list<RowError>  $errors
     */
    private function commitChunk(TransactionImport $import, array $transactions, array $errors, int $lastLine): void
    {
        $this->database->transaction(function () use ($import, $transactions, $errors, $lastLine): void {
            if ($transactions !== []) {
                $this->transactions->insertMany($import->user_id, $import->id, $transactions);
            }

            $this->imports->recordProgress($import, count($transactions), count($errors), $errors, $lastLine);
        });

        if ($transactions !== []) {
            $this->events->dispatch(new TransactionsImported($import->user_id, $import->id, count($transactions)));
        }
    }

    /**
     * Validates the header and counts data rows in a cheap streaming pass, so
     * clients can display real progress while the import runs.
     *
     * @throws InvalidCsvHeader
     */
    private function countDataRows(string $path): int
    {
        $count = 0;

        foreach ($this->dataRecords($path) as $ignored) {
            $count++;
        }

        return $count;
    }

    /**
     * @return iterable<int, list<string|null>>
     *
     * @throws InvalidCsvHeader
     */
    private function dataRecords(string $path): iterable
    {
        $stream = $this->files->readStream($path);

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
}
