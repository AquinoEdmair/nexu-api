<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->enum('type', ['deposit', 'referral']);
            $table->decimal('value', 10, 4);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('admins')->restrictOnDelete();
        });

        // Unique partial index: only one active config per type
        // Note: SQLite does not support partial indexes with WHERE, handled at service level
        // For PostgreSQL production: CREATE UNIQUE INDEX ON commission_configs(type) WHERE is_active = true
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_configs');
    }
};
