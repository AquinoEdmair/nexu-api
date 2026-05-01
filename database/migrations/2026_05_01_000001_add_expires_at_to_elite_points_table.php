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
        Schema::table('elite_points', function (Blueprint $table): void {
            // Last day of the month that is 12 months after the month points were earned.
            // Nullable so we can add the column before back-filling.
            $table->date('expires_at')->nullable()->after('description');
            $table->index('expires_at');
        });

        // Back-fill existing rows:
        // expires_at = last day of (created_at month + 12 months)
        DB::statement("
            UPDATE elite_points
            SET expires_at = (date_trunc('month', created_at) + interval '13 months' - interval '1 day')::date
            WHERE expires_at IS NULL
        ");

        // Make it non-nullable now that all rows are filled.
        Schema::table('elite_points', function (Blueprint $table): void {
            $table->date('expires_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('elite_points', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
