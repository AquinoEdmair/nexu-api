<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('reference_type', 50)->nullable()->after('notes');
            $table->uuid('reference_id')->nullable()->after('reference_type');

            // Composite indexes for the admin panel filters
            $table->index(['type', 'status', 'created_at'], 'idx_transactions_type_status_created');
            $table->index(['user_id', 'created_at'], 'idx_transactions_user_created');
            $table->index(['reference_type', 'reference_id'], 'idx_transactions_reference');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex('idx_transactions_type_status_created');
            $table->dropIndex('idx_transactions_user_created');
            $table->dropIndex('idx_transactions_reference');
            $table->dropColumn(['reference_type', 'reference_id']);
        });
    }
};
