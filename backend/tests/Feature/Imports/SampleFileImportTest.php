<?php

declare(strict_types=1);

use App\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * End-to-end through the HTTP layer with the real 15,000-row sample file.
 * The queue runs synchronously here, so the upload request processes it.
 * Expected totals were computed independently from the CSV with awk:
 *   awk -F, 'NR>1 { if ($4=="Receita") i+=$3; else e+=$3 } END { print i, e, i-e }'
 */
it('imports the real sample file and reports exact dashboard totals', function (): void {
    Storage::fake('local');
    $user = actingAsUser();

    $upload = new UploadedFile(sampleCsvPath(), 'financial_transactions.csv', null, null, true);

    $importId = $this->postJson('/api/imports', ['file' => $upload])
        ->assertAccepted()
        ->json('data.id');

    $this->getJson("/api/imports/{$importId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.total_rows', 15000)
        ->assertJsonPath('data.processed_rows', 15000)
        ->assertJsonPath('data.failed_rows', 0)
        ->assertJsonPath('data.errors', []);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertExactJson(['data' => [
            'income' => 3_611_960_974,
            'expense' => 2_670_954_574,
            'balance' => 941_006_400,
        ]]);

    $this->getJson('/api/transactions')
        ->assertOk()
        ->assertJsonPath('meta.total', 15000);

    expect(Transaction::query()->where('user_id', $user->id)->where('type', 'income')->count())->toBe(4509)
        ->and(Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->count())->toBe(10491);
});
