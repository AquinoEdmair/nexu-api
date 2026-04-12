<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elite_tiers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();
            $table->decimal('min_points', 18, 2);
            $table->decimal('max_points', 18, 2)->nullable(); // null = sin techo (último nivel)
            $table->decimal('multiplier', 5, 2)->default(1.00);
            $table->decimal('first_deposit_commission_rate', 5, 4)->default(0.0000);
            $table->decimal('recurring_commission_rate', 5, 4)->default(0.0000);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['min_points', 'max_points']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elite_tiers');
    }
};
