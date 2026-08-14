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
        Schema::create('user_bank_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('account_holder_name');

            // Sensitive fields encrypted at rest.
            $table->text('account_number_encrypted');

            // HMAC deterministic fingerprint for duplicate protection.
            $table->string('account_number_fingerprint')->index();

            $table->string('routing_number')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('account_type')->nullable(); // Current, Savings, etc.

            $table->boolean('is_primary')->default(false);
            $table->string('verification_status')->default('pending'); // pending, verified, rejected

            $table->timestamps();

            // Deduplication constraint per user (PA-2D-02)
            $table->unique(['user_id', 'account_number_fingerprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_bank_accounts');
    }
};
