<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_user_status', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->integer('view_count')->default(0);
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('email_opened_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_user_status');
    }
};
