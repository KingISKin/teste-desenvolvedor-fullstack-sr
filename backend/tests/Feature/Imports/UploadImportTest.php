<?php

declare(strict_types=1);

use App\Domain\Imports\Contracts\ImportProcessingQueue;
use App\Domain\Imports\Enums\ImportStatus;
use App\Jobs\ProcessTransactionImport;
use App\Models\TransactionImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Queue::fake();
    Storage::fake('local');
});

it('stores the file privately, records a pending import and queues the job', function (): void {
    $user = actingAsUser();
    $file = UploadedFile::fake()->createWithContent('my transactions.csv', csv([
        '2026-05-01,Mensalidade Cliente B #869,115346,Receita',
    ]));

    $response = $this->postJson('/api/imports', ['file' => $file]);

    $import = TransactionImport::query()->sole();

    $response->assertAccepted()
        ->assertHeader('Location', route('imports.show', $import))
        ->assertExactJson(['data' => [
            'id' => $import->id,
            'status' => 'pending',
            'total_rows' => null,
            'processed_rows' => 0,
            'failed_rows' => 0,
            'errors' => [],
            'created_at' => $import->created_at->toIso8601String(),
            'finished_at' => null,
        ]]);

    expect($import->user_id)->toBe($user->id)
        ->and($import->status)->toBe(ImportStatus::Pending)
        ->and($import->original_filename)->toBe('my transactions.csv')
        ->and($import->stored_path)->toStartWith("imports/{$user->id}/")
        ->and($import->stored_path)->not->toContain('my transactions');

    Storage::disk('local')->assertExists($import->stored_path);

    Queue::assertPushed(
        ProcessTransactionImport::class,
        fn (ProcessTransactionImport $job): bool => $job->importId === $import->id,
    );
    Queue::assertCount(1);
});

it('accepts a .csv file whose content is sniffed as plain text', function (): void {
    actingAsUser();
    $file = realUpload('export.csv', csv(['2026-05-01,X,1,Receita']));

    $this->postJson('/api/imports', ['file' => $file])->assertAccepted();

    Queue::assertPushed(ProcessTransactionImport::class);
});

it('rejects invalid uploads without queueing anything', function (Closure $payload, string $expectedError): void {
    actingAsUser();

    $this->postJson('/api/imports', $payload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file' => $expectedError]);

    Queue::assertNothingPushed();
    expect(TransactionImport::query()->count())->toBe(0);
})->with([
    'missing file' => [fn (): array => [], 'The file field is required.'],
    'not a file' => [fn (): array => ['file' => 'date,description'], 'The file field must be a file.'],
    'empty file' => [fn (): array => ['file' => UploadedFile::fake()->createWithContent('empty.csv', '')], 'The file is empty.'],
    'txt extension' => [fn (): array => ['file' => UploadedFile::fake()->createWithContent('export.txt', csv(['2026-05-01,X,1,Receita']))], 'The file must be a .csv file.'],
    'image' => [fn (): array => ['file' => UploadedFile::fake()->image('photo.png')], 'The file must be a .csv file.'],
    // A real file (fakes report a MIME type derived from the name, not sniffed).
    'binary content named .csv' => [fn (): array => ['file' => realUpload('data.csv', "\x89PNG\r\n\x1a\n".str_repeat("\0", 64))], 'The file content must be plain-text CSV.'],
    'too large' => [fn (): array => ['file' => UploadedFile::fake()->create('big.csv', 20481, 'text/csv')], 'The file field must not be greater than 20480 kilobytes.'],
]);

it('requires authentication to upload', function (): void {
    $this->postJson('/api/imports', ['file' => UploadedFile::fake()->create('a.csv')])
        ->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('marks the import as failed and removes the file when it cannot be queued', function (): void {
    actingAsUser();
    app()->instance(ImportProcessingQueue::class, new class implements ImportProcessingQueue
    {
        public function push(int $importId): void
        {
            throw new RuntimeException('Redis is down.');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/api/imports', [
        'file' => UploadedFile::fake()->createWithContent('a.csv', csv(['2026-05-01,X,1,Receita'])),
    ]))->toThrow(RuntimeException::class, 'Redis is down.');

    $import = TransactionImport::query()->sole();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->errors)->toBe([['line' => 0, 'message' => 'The import could not be queued. Please try again.']])
        ->and($import->finished_at)->not->toBeNull();

    Storage::disk('local')->assertMissing($import->stored_path);
});

it('limits each user to 10 uploads per minute', function (): void {
    actingAsUser();
    $upload = fn () => $this->postJson('/api/imports', [
        'file' => UploadedFile::fake()->createWithContent('a.csv', csv(['2026-05-01,X,1,Receita'])),
    ]);

    foreach (range(1, 10) as $attempt) {
        $upload()->assertAccepted();
    }

    $upload()->assertTooManyRequests();
    Queue::assertCount(10);

    // Another user has an independent budget.
    actingAsUser();
    $upload()->assertAccepted();
});

function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}
