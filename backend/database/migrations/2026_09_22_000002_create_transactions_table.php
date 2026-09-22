<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_import_id')->nullable()->constrained()->nullOnDelete();
            $table->date('transaction_date');
            $table->string('description');
            // Integer cents: money is never represented as a float.
            $table->unsignedBigInteger('amount');
            $table->string('type', 16);
            $table->timestamps();

            // Serves the paginated listing (WHERE user_id ORDER BY date DESC, id DESC).
            $table->index(['user_id', 'transaction_date', 'id']);
            // Covering index for the dashboard aggregate: SUM(amount) per type
            // is answered from the index alone, without touching table rows.
            $table->index(['user_id', 'type', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
