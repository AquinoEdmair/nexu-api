<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deposit_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deposit_address_id')->constrained('deposit_addresses');

            // Snapshots at creation time so address changes don't affect history
            $table->string('currency', 20);
            $table->string('network', 40)->nullable();
            $table->string('address', 255);
            $table->string('qr_image_path')->nullable();

            $table->decimal('amount_expected', 18, 8);
            $table->string('tx_hash', 255)->nullable();

            $table->enum('status', ['pending', 'client_confirmed', 'completed', 'cancelled'])
                  ->default('pending');

            $table->timestamp('client_confirmed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('id')->on('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_requests');
    }
};
