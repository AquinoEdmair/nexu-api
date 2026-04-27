<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('elite_points', function (Blueprint $table) {
            $table->uuid('source_user_id')->nullable()->after('transaction_id');
            $table->foreign('source_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('elite_points', function (Blueprint $table) {
            $table->dropForeign(['source_user_id']);
            $table->dropColumn('source_user_id');
        });
    }
};
