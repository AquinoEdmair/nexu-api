<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For PostgreSQL, an enum is implemented as a check constraint on a VARCHAR column
        // We need to drop the old check constraint and recreate it with the new allowed values
        
        // This migration adds 'investment' to the allowed types
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type::text = ANY (ARRAY['deposit'::text, 'withdrawal'::text, 'commission'::text, 'yield'::text, 'referral_commission'::text, 'investment'::text]))");
        
        // Let's also ensure 'status' includes everything needed, though current error is type related
        // The current status are: ['pending', 'confirmed', 'rejected', 'processing']
        // No changes needed for status yet based on the error.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original allowed values
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type::text = ANY (ARRAY['deposit'::text, 'withdrawal'::text, 'commission'::text, 'yield'::text, 'referral_commission'::text]))");
    }
};
