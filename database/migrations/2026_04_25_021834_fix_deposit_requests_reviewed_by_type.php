<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn('reviewed_by');
        });

        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->uuid('reviewed_by')->nullable()->after('client_confirmed_at');
            $table->foreign('reviewed_by')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn('reviewed_by');
        });

        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('client_confirmed_at');
        });
    }
};
