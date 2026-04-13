<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_invoices', function (Blueprint $table): void {
            // Crypto amount the user must send (e.g. 0.00156 BTC)
            $table->decimal('pay_amount', 18, 8)->nullable()->after('amount_expected');
        });
    }

    public function down(): void
    {
        Schema::table('deposit_invoices', function (Blueprint $table): void {
            $table->dropColumn('pay_amount');
        });
    }
};
