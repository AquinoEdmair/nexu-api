<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yield_logs', function (Blueprint $table): void {
            $table->enum('negative_policy', ['skip', 'floor'])
                ->default('floor')
                ->after('scope');

            $table->uuid('specific_user_id')
                ->nullable()
                ->after('negative_policy');

            // For "¿hay algún yield en processing ahora?"
            $table->index(['status', 'applied_at'], 'idx_yield_logs_status_applied_at');
        });

        Schema::table('yield_log_users', function (Blueprint $table): void {
            // Extend status to include 'skipped' (policy=skip with insufficient balance)
            $table->enum('status', ['applied', 'skipped', 'failed'])
                ->default('applied')
                ->change();

            // For per-user yield history
            $table->index(['user_id', 'created_at'], 'idx_yield_log_users_user_id_created');
        });
    }

    public function down(): void
    {
        Schema::table('yield_log_users', function (Blueprint $table): void {
            $table->dropIndex('idx_yield_log_users_user_id_created');
            $table->enum('status', ['applied', 'failed'])->default('applied')->change();
        });

        Schema::table('yield_logs', function (Blueprint $table): void {
            $table->dropIndex('idx_yield_logs_status_applied_at');
            $table->dropColumn(['negative_policy', 'specific_user_id']);
        });
    }
};
