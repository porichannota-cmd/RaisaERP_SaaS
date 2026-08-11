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
        Schema::create('position_assignments', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('membership_id', 26);
            $table->char('position_id', 26);
            $table->string('reference_number', 50)->unique();
            $table->enum('status', ['active', 'ended', 'cancelled'])->default('active');

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->unsignedBigInteger('ended_by')->nullable();
            $table->string('ended_reason')->nullable();

            $table->timestamps();

            $table->foreign('membership_id')->references('id')->on('tenant_memberships')->onDelete('cascade');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('cascade');
            $table->foreign('ended_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['membership_id', 'status']);
            $table->index(['membership_id', 'effective_from', 'effective_to'], 'idx_pa_effective');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_assignments');
    }
};
