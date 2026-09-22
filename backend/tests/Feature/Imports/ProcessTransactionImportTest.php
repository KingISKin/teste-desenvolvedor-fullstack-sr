<?php

declare(strict_types=1);

use App\Domain\Dashboard\Actions\GetDashboardSummary;
use App\Domain\Imports\Actions\ImportTransactionsFromCsv;
use App\Domain\Imports\Csv\TransactionCsvRowParser;
use App\Domain\Imports\Enums\ImportStatus;
use App\Domain\Transactions\Contracts\TransactionRepository;
use App\Domain\Transactions\Enums\TransactionType;
use App\Domain\Transactions\Events\TransactionsImported;
use App\Domain\Transactions\Events\TransactionsRolledBack;
use App\Infrastructure\Persistence\EloquentTransactionRepository;
use App\Jobs\ProcessTransactionImport;
use App\Models\Transaction;
use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function storedImport(string $content, ?User $user = null): TransactionImport
{
    $import = TransactionImport::factory()
        ->for($user ?? User::factory()->create())
        ->create(['stored_path' => 'imports/test.csv']);

    Storage::disk('local')->put($import->stored_path, $content);

    return $import;
}

it('imports valid rows, records invalid ones and completes', function (): void {
    $import = storedImport(csv([
        '2026-05-01,Mensalidade Cliente B #869,115346,Receita',
        '2025-11-15,Impostos e Taxas #464,433209,Despesa',
        '2026-13-01,Bad date,100,Receita',
        '',
        '2026-01-10,"Serviços, Prestados",471285,Receita',
        '2026-01-11,Negative,-5,Despesa',
    ]));

    ProcessTransactionImport::dispatchSync($import->id);

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->total_rows)->toBe(5)
        ->and($import->processed_rows)->toBe(3)
        ->and($import->failed_rows)->toBe(2)
        ->and($import->started_at)->not->toBeNull()
        ->and($import->finished_at)->not->toBeNull()
        ->and($import->errors)->toBe([
            ['line' => 4, 'message' => 'Date must be a valid calendar date in YYYY-MM-DD format.'],
            ['line' => 7, 'message' => 'Amount must be a positive integer number of cents.'],
        ]);

    $transactions = Transaction::query()->orderBy('id')->get();

    expect($transactions)->toHaveCount(3)
        ->and($transactions->pluck('user_id')->unique()->all())->toBe([$import->user_id])
        ->and($transactions->pluck('transaction_import_id')->unique()->all())->toBe([$import->id])
        ->and($transactions[0]->transaction_date->toDateString())->toBe('2026-05-01')
        ->and($transactions[0]->amount)->toBe(115346)
        ->and($transactions[0]->type)->toBe(TransactionType::Income)
        ->and($transactions[1]->type)->toBe(TransactionType::Expense)
        ->and($transactions[2]->description)->toBe('Serviços, Prestados');

    // Processed uploads are removed from storage.
    Storage::disk('local')->assertMissing($import->stored_path);
});

it('fails the import without retrying when the header is invalid', function (): void {
    $import = storedImport("data,descricao,valor,tipo\n2026-05-01,X,100,Receita\n");

    ProcessTransactionImport::dispatchSync($import->id);

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->errors)->toBe([
            ['line' => 1, 'message' => 'Invalid header. Expected exactly: date,description,amount,type.'],
        ])
        ->and($import->finished_at)->not->toBeNull()
        ->and(Transaction::query()->count())->toBe(0);

    // The file is kept for troubleshooting.
    Storage::disk('local')->assertExists($import->stored_path);
});

it('fails an empty file', function (): void {
    $import = storedImport('');

    ProcessTransactionImport::dispatchSync($import->id);

    expect($import->refresh()->status)->toBe(ImportStatus::Failed);
});

it('completes a header-only file with zero rows', function (): void {
    $import = storedImport(csv([]));

    ProcessTransactionImport::dispatchSync($import->id);

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->total_rows)->toBe(0)
        ->and($import->processed_rows)->toBe(0);
});

it('fails when the stored file is missing', function (): void {
    $import = TransactionImport::factory()->create(['stored_path' => 'imports/missing.csv']);

    ProcessTransactionImport::dispatchSync($import->id);

    expect($import->refresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->errors)->toBe([['line' => 0, 'message' => 'The uploaded file could not be found on the server. Please upload it again.']]);
});

it('inserts in chunks and dispatches one event per committed chunk', function (): void {
    Event::fake([TransactionsImported::class]);
    config(['imports.chunk_size' => 2]);

    $import = storedImport(csv([
        '2026-01-01,A,100,Receita',
        '2026-01-02,B,200,Despesa',
        '2026-01-03,C,300,Receita',
        '2026-01-04,D,400,Despesa',
        '2026-01-05,E,500,Receita',
    ]));

    ProcessTransactionImport::dispatchSync($import->id);

    expect(Transaction::query()->count())->toBe(5)
        ->and($import->refresh()->last_processed_line)->toBe(6);

    Event::assertDispatchedTimes(TransactionsImported::class, 3);
    Event::assertDispatched(
        TransactionsImported::class,
        fn (TransactionsImported $event): bool => $event->userId === $import->user_id
            && $event->importId === $import->id
            && $event->count === 2,
    );
});

it('does not dispatch the event when no valid row was persisted', function (): void {
    Event::fake([TransactionsImported::class]);

    $import = storedImport(csv(['invalid,row,x,y']));

    ProcessTransactionImport::dispatchSync($import->id);

    expect($import->refresh()->failed_rows)->toBe(1);
    Event::assertNotDispatched(TransactionsImported::class);
});

it('resumes from the checkpoint on retry without duplicating rows', function (): void {
    config(['imports.chunk_size' => 2]);

    // Fails once while inserting the second chunk, like a DB hiccup mid-import.
    app()->instance(TransactionRepository::class, new class(new EloquentTransactionRepository) implements TransactionRepository
    {
        private int $calls = 0;

        public function __construct(private readonly EloquentTransactionRepository $inner) {}

        public function insertMany(int $userId, int $importId, array $transactions): void
        {
            if (++$this->calls === 2) {
                throw new RuntimeException('Lost connection during insert.');
            }

            $this->inner->insertMany($userId, $importId, $transactions);
        }

        public function deleteForImport(int $importId): int
        {
            return $this->inner->deleteForImport($importId);
        }

        public function paginateForUser(int $userId, int $perPage): Illuminate\Contracts\Pagination\LengthAwarePaginator
        {
            return $this->inner->paginateForUser($userId, $perPage);
        }

        public function totalsForUser(int $userId): App\Domain\Transactions\DTOs\TransactionTotals
        {
            return $this->inner->totalsForUser($userId);
        }
    });

    $import = storedImport(csv([
        '2026-01-01,A,100,Receita',
        '2026-01-02,B,200,Despesa',
        '2026-01-03,C,300,Receita',
        'broken,row,x,y',
        '2026-01-05,E,500,Receita',
    ]));

    // Attempt 1 (the queue would retry it later).
    expect(fn () => app(ImportTransactionsFromCsv::class)->handle($import->id))
        ->toThrow(RuntimeException::class, 'Lost connection during insert.');

    expect($import->refresh()->status)->toBe(ImportStatus::Processing)
        ->and($import->last_processed_line)->toBe(3)
        ->and($import->processed_rows)->toBe(2)
        ->and(Transaction::query()->count())->toBe(2);

    // Attempt 2 resumes after line 3.
    app(ImportTransactionsFromCsv::class)->handle($import->id);

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->processed_rows)->toBe(4)
        ->and($import->failed_rows)->toBe(1)
        ->and($import->errors)->toHaveCount(1)
        ->and(Transaction::query()->count())->toBe(4)
        ->and(Transaction::query()->pluck('description')->all())->toBe(['A', 'B', 'C', 'E']);
});

it('ignores redelivery of an already completed import', function (): void {
    $import = storedImport(csv(['2026-01-01,A,100,Receita']));

    ProcessTransactionImport::dispatchSync($import->id);
    ProcessTransactionImport::dispatchSync($import->id);

    expect(Transaction::query()->count())->toBe(1);
});

it('ignores jobs for imports that no longer exist', function (): void {
    ProcessTransactionImport::dispatchSync(123456);

    expect(TransactionImport::query()->count())->toBe(0);
});

it('caps the number of stored row errors', function (): void {
    config(['imports.max_stored_errors' => 3]);

    $import = storedImport(csv(array_fill(0, 10, 'bad,row,x,y')));

    ProcessTransactionImport::dispatchSync($import->id);

    $import->refresh();

    expect($import->failed_rows)->toBe(10)
        ->and($import->errors)->toHaveCount(3);
});

it('marks the import as failed once all attempts are exhausted', function (): void {
    $import = TransactionImport::factory()->create();

    (new ProcessTransactionImport($import->id))->failed(new RuntimeException('Internal detail'));

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->finished_at)->not->toBeNull()
        ->and($import->errors)->toBe([
            ['line' => 0, 'message' => 'The import could not be processed and no rows were saved. Please upload the file again.'],
        ]);
});

it('rolls back rows committed by earlier attempts when the job finally fails', function (): void {
    Event::fake([TransactionsRolledBack::class]);
    $user = User::factory()->create();

    $kept = storedImport(csv(['2026-01-03,Kept,300,Receita']), $user);
    ProcessTransactionImport::dispatchSync($kept->id);

    // An attempt committed its first chunk and then crashed.
    $import = TransactionImport::factory()->for($user)->create([
        'status' => ImportStatus::Processing,
        'total_rows' => 2,
        'processed_rows' => 1,
        'last_processed_line' => 2,
    ]);
    app(TransactionRepository::class)->insertMany($user->id, $import->id, [
        (new TransactionCsvRowParser)->parse(['2026-01-01', 'A', '100', 'Receita']),
    ]);

    (new ProcessTransactionImport($import->id))->failed(new RuntimeException('Gave up'));

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->processed_rows)->toBe(0)
        ->and($import->last_processed_line)->toBe(0)
        ->and(Transaction::query()->pluck('description')->all())->toBe(['Kept']);

    Event::assertDispatched(
        TransactionsRolledBack::class,
        fn (TransactionsRolledBack $event): bool => $event->userId === $user->id
            && $event->importId === $import->id
            && $event->count === 1,
    );
});

it('invalidates the dashboard cache when an import is rolled back', function (): void {
    $user = User::factory()->create();
    $import = TransactionImport::factory()->for($user)->create(['status' => ImportStatus::Processing]);
    Transaction::factory()->for($user)->income(700)->create(['transaction_import_id' => $import->id]);

    $summary = fn () => app(GetDashboardSummary::class)->handle($user);
    expect($summary()->income)->toBe(700);

    (new ProcessTransactionImport($import->id))->failed(new RuntimeException);

    expect($summary()->income)->toBe(0);
});

it('does not overwrite a finished import when a late failure arrives', function (): void {
    $import = TransactionImport::factory()->create(['status' => ImportStatus::Completed]);

    (new ProcessTransactionImport($import->id))->failed(new RuntimeException);

    expect($import->refresh()->status)->toBe(ImportStatus::Completed);
});

it('declares a bounded retry policy', function (): void {
    $job = new ProcessTransactionImport(1);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 30])
        ->and($job->backoff)->toHaveCount($job->tries - 1)
        ->and($job->timeout)->toBe(600)
        ->and($job->failOnTimeout)->toBeTrue()
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($job->middleware()[0]->expiresAfter)->toBeLessThan(config('queue.connections.redis.retry_after'));
});
