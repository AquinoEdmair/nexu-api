<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove balance_available from wallets and balance_snapshots.
 *
 * All funds are now permanently in operation. balance_total becomes
 * an alias for balance_in_operation so existing queries don't break.
 *
 * Before dropping, syncs balance_total = balance_in_operation so the
 * column keeps meaning the same thing.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sync balance_total = balance_in_operation before dropping available
        DB::statement('UPDATE wallets SET balance_total = balance_in_operation');
        DB::statement('UPDATE balance_snapshots SET balance_total = balance_in_operation');

        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn('balance_available');
        });

        Schema::table('balance_snapshots', function (Blueprint $table): void {
            $table->dropColumn('balance_available');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->decimal('balance_available', 18, 8)->default('0.00000000')->after('id');
        });

        Schema::table('balance_snapshots', function (Blueprint $table): void {
            $table->decimal('balance_available', 18, 8)->default('0.00000000')->after('user_id');
        });
    }
};
