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
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
        } else {
            Schema::table('notifications', function (Blueprint $table) {
                $table->json('data')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text');
        } else {
            Schema::table('notifications', function (Blueprint $table) {
                $table->text('data')->change();
            });
        }
    }
};
