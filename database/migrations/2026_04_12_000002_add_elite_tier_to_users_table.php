<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('elite_tier_id')->nullable()->after('referred_by');
            $table->boolean('elite_tier_manual_override')->default(false)->after('elite_tier_id');

            $table->foreign('elite_tier_id')
                ->references('id')
                ->on('elite_tiers')
                ->nullOnDelete();

            $table->index('elite_tier_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['elite_tier_id']);
            $table->dropIndex(['elite_tier_id']);
            $table->dropColumn(['elite_tier_id', 'elite_tier_manual_override']);
        });
    }
};
