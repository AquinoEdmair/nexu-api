<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yield_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('applied_by');
            $table->enum('type', ['percentage', 'fixed_amount']);
            $table->decimal('value', 10, 4);
            $table->enum('scope', ['all', 'specific_user'])->default('all');
            $table->enum('status', ['draft', 'processing', 'completed', 'failed'])->default('draft');
            $table->integer('users_count')->default(0);
            $table->decimal('total_applied', 18, 8)->default(0);
            $table->text('description')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('applied_by')->references('id')->on('admins')->restrictOnDelete();
            $table->index('status');
        });

        Schema::create('yield_log_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('yield_log_id');
            $table->uuid('user_id');
            $table->decimal('balance_before', 18, 8);
            $table->decimal('balance_after', 18, 8);
            $table->decimal('amount_applied', 18, 8);
            $table->enum('status', ['applied', 'failed'])->default('applied');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('yield_log_id')->references('id')->on('yield_logs')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('yield_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yield_log_users');
        Schema::dropIfExists('yield_logs');
    }
};
