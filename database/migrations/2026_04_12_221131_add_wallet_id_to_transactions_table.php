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
            $table->uuid('wallet_id')->nullable()->after('user_id');
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
            $table->index('wallet_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['wallet_id']);
            $table->dropColumn('wallet_id');
        });
    }
};
