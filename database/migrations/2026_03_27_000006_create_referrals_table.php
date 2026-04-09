<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('referrer_id');
            $table->uuid('referred_id')->unique();
            $table->decimal('commission_rate', 5, 4);
            $table->decimal('total_earned', 18, 8)->default(0);
            $table->timestamps();

            $table->foreign('referrer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('referred_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('referrer_id');
        });

        Schema::create('elite_points', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->decimal('points', 18, 2)->default(0);
            $table->uuid('transaction_id')->nullable();
            $table->string('description', 255);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elite_points');
        Schema::dropIfExists('referrals');
    }
};
