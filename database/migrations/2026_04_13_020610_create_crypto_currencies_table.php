<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_currencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 80);                   // "Bitcoin"
            $table->string('symbol', 20)->unique();       // "BTC"
            $table->string('now_payments_code', 40);      // "btc" | "usdttrc20"
            $table->string('network', 40)->nullable();    // "Bitcoin" | "TRC20"
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_currencies');
    }
};
