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
        Schema::create('platform_reviewer_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnDelete();
            $table->string('capability'); // e.g. ACCOUNT_REVIEW
            $table->string('status')->default('ACTIVE'); // ACTIVE, REVOKED
            $table->foreignIdFor(\App\Models\User::class, 'granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->useCurrent();
            $table->foreignIdFor(\App\Models\User::class, 'revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // Prevent duplicate active assignments for the same capability per user
            $table->unique(['user_id', 'capability', 'status'], 'idx_plat_rev_user_cap_stat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_reviewer_assignments');
    }
};
