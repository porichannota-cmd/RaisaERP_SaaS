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
        Schema::create('user_mfs_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('provider'); // bKash, Nagad, Rocket, Upay, CellFin, etc.

            // Sensitive fields encrypted at rest (PA-2D-05)
            $table->text('mobile_encrypted');
            $table->string('mobile_fingerprint')->index();

            $table->string('account_name')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->string('verification_status')->default('pending');

            $table->timestamps();

            // Deduplication constraint per user (PA-2D-03)
            $table->unique(['user_id', 'provider', 'mobile_fingerprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_mfs_accounts');
    }
};
