<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_locks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('type', 50); // deposit, yield, referral_commission, commission
            $table->integer('days')->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'type'], 'idx_user_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_locks');
    }
};
