<?php

declare(strict_types=1);

use App\Domain\Imports\Enums\ImportStatus;
use App\Models\TransactionImport;
use Illuminate\Support\Carbon;

it('shows the progress of an own import', function (): void {
    $user = actingAsUser();
    $import = TransactionImport::factory()->for($user)->create([
        'status' => ImportStatus::Completed,
        'total_rows' => 3,
        'processed_rows' => 2,
        'failed_rows' => 1,
        'errors' => [['line' => 3, 'message' => 'Amount must be a positive integer number of cents.']],
        'finished_at' => Carbon::parse('2026-09-22 10:00:00'),
    ]);

    $this->getJson("/api/imports/{$import->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $import->id)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.total_rows', 3)
        ->assertJsonPath('data.processed_rows', 2)
        ->assertJsonPath('data.failed_rows', 1)
        ->assertJsonPath('data.errors', [['line' => 3, 'message' => 'Amount must be a positive integer number of cents.']])
        ->assertJsonPath('data.finished_at', '2026-09-22T10:00:00+00:00')
        ->assertJsonMissingPath('data.stored_path');
});

it('hides imports of other users behind a 404', function (): void {
    actingAsUser();
    $foreignImport = TransactionImport::factory()->create();

    $this->getJson("/api/imports/{$foreignImport->id}")->assertNotFound();
});

it('returns 404 for unknown imports', function (): void {
    actingAsUser();

    $this->getJson('/api/imports/999999')->assertNotFound();
    $this->getJson('/api/imports/abc')->assertNotFound();
});
