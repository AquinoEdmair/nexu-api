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
        Schema::create('deposit_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('currency_id')->constrained('deposit_currencies')->cascadeOnDelete();
            $table->string('address', 255);
            $table->string('qr_image_path')->nullable();
            $table->string('label', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['currency_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_addresses');
    }
};
