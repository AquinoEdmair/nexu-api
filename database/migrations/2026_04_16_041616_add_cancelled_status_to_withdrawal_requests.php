<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL stores Blueprint::enum() as varchar + CHECK constraint
        DB::statement("ALTER TABLE withdrawal_requests DROP CONSTRAINT IF EXISTS withdrawal_requests_status_check");
        DB::statement("ALTER TABLE withdrawal_requests ADD CONSTRAINT withdrawal_requests_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'approved'::text, 'rejected'::text, 'processing'::text, 'completed'::text, 'cancelled'::text]))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE withdrawal_requests DROP CONSTRAINT IF EXISTS withdrawal_requests_status_check");
        DB::statement("ALTER TABLE withdrawal_requests ADD CONSTRAINT withdrawal_requests_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'approved'::text, 'rejected'::text, 'processing'::text, 'completed'::text]))");
    }
};
