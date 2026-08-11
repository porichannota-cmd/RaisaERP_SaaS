<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enforce role invariants at the database level
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE roles ADD CONSTRAINT chk_role_tenant_invariants CHECK (
                (type = 'platform_system' AND tenant_id IS NULL) OR
                (type != 'platform_system' AND tenant_id IS NOT NULL)
            )");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE roles DROP CONSTRAINT chk_role_tenant_invariants');
        }
    }
};
