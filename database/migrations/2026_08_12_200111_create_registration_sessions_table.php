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
        Schema::create('registration_sessions', function (Blueprint $table) {
            $table->char('id', 26)->primary();

            // External reference for API/frontend, safe to expose
            $table->string('public_reference', 50)->nullable()->unique();

            // HMAC-SHA256 of the token. Raw token is never stored.
            $table->char('token_hash', 64)->unique();

            // The mobile number being verified.
            $table->string('mobile_canonical', 20)->index();
            $table->string('email', 255)->nullable();

            // The source that initiated the registration (e.g., 'public', 'invitation').
            $table->string('registration_source', 30);

            // Current state of the session.
            $table->string('status', 30);

            // Link to the OTP verification if applicable.
            $table->char('otp_record_id', 26)->nullable();

            // Timestamps for lifecycle management.
            $table->timestamp('otp_verified_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            // Safe fingerprinting.
            $table->char('ip_hash', 64)->nullable();

            // Extensible metadata.
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_sessions');
    }
};
