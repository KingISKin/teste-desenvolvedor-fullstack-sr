<?php

declare(strict_types=1);

use App\Domain\Dashboard\Actions\GetDashboardSummary;
use App\Domain\Imports\Actions\FailTransactionImport;
use App\Domain\Imports\Enums\ImportStatus;
use App\Domain\Transactions\Events\TransactionsRolledBack;
use App\Jobs\ProcessTransactionImport;
use App\Models\Transaction;
use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
 * FailTransactionImport rolls back every open transaction on the connection,
 * so these tests cannot run inside RefreshDatabase's wrapping transaction
 * (tests/Pest.php gives this directory DatabaseMigrations instead).
 */

const ROLLED_BACK_MESSAGE = 'The import could not be processed and no rows were saved. Please upload the file again.';

it('marks the import as failed once all attempts are exhausted', function (): void {
    $import = TransactionImport::factory()->create();

    (new ProcessTransactionImport($import->id))->failed(new RuntimeException('Internal detail'));

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->finished_at)->not->toBeNull()
        ->and($import->errors)->toBe([['line' => 0, 'message' => ROLLED_BACK_MESSAGE]]);
});

it('rolls back rows committed by earlier attempts and resets the progress', function (): void {
    Event::fake([TransactionsRolledBack::class]);
    $user = User::factory()->create();
    Transaction::factory()->for($user)->create(['description' => 'Kept']);

    // An attempt committed one chunk (one valid, one rejected row) and crashed.
    $import = TransactionImport::factory()->for($user)->create([
        'status' => ImportStatus::Processing,
        'total_rows' => 3,
        'processed_rows' => 1,
        'failed_rows' => 1,
        'errors' => [['line' => 3, 'message' => 'Amount must be a positive integer number of cents.']],
        'last_processed_line' => 3,
    ]);
    Transaction::factory()->for($user)->create(['transaction_import_id' => $import->id]);

    (new ProcessTransactionImport($import->id))->failed(new RuntimeException('Gave up'));

    $import->refresh();

    // Nothing of this import was saved, so no row is reported as rejected either.
    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->processed_rows)->toBe(0)
        ->and($import->failed_rows)->toBe(0)
        ->and($import->last_processed_line)->toBe(0)
        ->and($import->errors)->toBe([['line' => 0, 'message' => ROLLED_BACK_MESSAGE]])
        ->and(Transaction::query()->pluck('description')->all())->toBe(['Kept']);

    Event::assertDispatched(
        TransactionsRolledBack::class,
        fn (TransactionsRolledBack $event): bool => $event->userId === $user->id
            && $event->importId === $import->id
            && $event->count === 1,
    );
});

it('discards an open chunk transaction before rolling the import back', function (): void {
    $import = TransactionImport::factory()->create(['status' => ImportStatus::Processing]);
    Transaction::factory()->for($import->user)->create(['transaction_import_id' => $import->id]);

    // The job timeout fires while a chunk insert is still uncommitted.
    DB::beginTransaction();
    Transaction::factory()->for($import->user)->create(['transaction_import_id' => $import->id]);

    app(FailTransactionImport::class)->handle($import->id);

    // Everything below is read outside any transaction: the result is durable.
    expect(DB::transactionLevel())->toBe(0)
        ->and(Transaction::query()->where('transaction_import_id', $import->id)->count())->toBe(0)
        ->and($import->refresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->errors)->toBe([['line' => 0, 'message' => ROLLED_BACK_MESSAGE]]);
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
