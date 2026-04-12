<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add fee/net columns to withdrawal_requests ────────────────────
        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            $table->decimal('fee_amount', 18, 8)->default(0)->after('amount');
            $table->decimal('net_amount', 18, 8)->default(0)->after('fee_amount');
            $table->decimal('commission_rate', 10, 4)->default(0)->after('net_amount');
        });

        // ── 2. Remove referral configs (not needed in this module) ────────────
        DB::table('commission_configs')->where('type', 'referral')->delete();

        // ── 3. Change ENUM type: referral → withdrawal ───────────────────────
        // SQLite (local/test): recreate column via raw
        // PostgreSQL (production): ALTER TABLE ... USING
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE commission_configs DROP CONSTRAINT IF EXISTS commission_configs_type_check");
            DB::statement("ALTER TABLE commission_configs ADD CONSTRAINT commission_configs_type_check CHECK (type IN ('deposit', 'withdrawal'))");
        }
        // SQLite doesn't enforce ENUMs — the delete above is enough
        // MySQL: modify column
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE commission_configs MODIFY COLUMN type ENUM('deposit','withdrawal') NOT NULL");
        }

        // ── 4. Create admin_commission_ledger ─────────────────────────────────
        Schema::create('admin_commission_ledger', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->enum('source_type', ['deposit', 'withdrawal']);
            $table->uuid('source_id')->comment('FK to transactions.id or withdrawal_requests.id');
            $table->uuid('user_id');
            $table->decimal('amount', 18, 8);
            $table->decimal('commission_rate', 10, 4);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['source_type', 'source_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_commission_ledger');

        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            $table->dropColumn(['fee_amount', 'net_amount', 'commission_rate']);
        });

        // Restore referral enum (best effort)
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE commission_configs MODIFY COLUMN type ENUM('deposit','referral') NOT NULL");
        }
    }
};
