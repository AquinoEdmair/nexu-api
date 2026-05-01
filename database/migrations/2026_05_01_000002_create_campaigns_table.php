<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->enum('type', ['informative', 'action']);
            $table->enum('channel', ['modal', 'email', 'both']);
            $table->enum('target_segment', ['all', 'has_balance', 'no_deposit', 'referred', 'active', 'inactive', 'custom']);
            $table->json('custom_target_query')->nullable();
            $table->string('cta_text', 100)->nullable();
            $table->string('cta_url')->nullable();
            $table->enum('cta_type', ['redirect', 'api_action', 'none'])->default('none');
            $table->integer('priority')->default(0);
            $table->enum('display_frequency', ['once', 'until_accepted', 'always'])->default('once');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
