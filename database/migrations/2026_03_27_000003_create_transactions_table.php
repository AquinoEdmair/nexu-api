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
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->enum('type', ['deposit', 'withdrawal', 'commission', 'yield', 'referral_commission']);
            $table->decimal('amount', 18, 8);
            $table->decimal('fee_amount', 18, 8)->default(0);
            $table->decimal('net_amount', 18, 8);
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'processing']);
            $table->string('currency', 10);
            $table->string('external_tx_id', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('type');
            $table->index('status');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('external_tx_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
