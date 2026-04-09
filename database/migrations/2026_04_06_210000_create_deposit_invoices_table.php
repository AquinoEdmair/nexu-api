<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('invoice_id', 255)->unique();
            $table->string('currency', 10);
            $table->string('network', 50)->nullable();
            $table->string('address', 255);
            $table->text('qr_code_url')->nullable();
            $table->string('status', 20)->default('awaiting_payment');
            $table->decimal('amount_expected', 18, 8)->nullable();
            $table->decimal('amount_received', 18, 8)->nullable();
            $table->string('tx_hash', 255)->nullable();
            $table->uuid('transaction_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_invoices');
    }
};
