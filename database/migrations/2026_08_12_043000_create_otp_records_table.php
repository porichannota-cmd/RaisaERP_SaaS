<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_records', function (Blueprint $table) {
            // Primary key: ULID (char 26)
            $table->char('id', 26)->primary();

            // Tenant context — nullable for platform-level flows (registration before tenant exists)
            $table->char('tenant_id', 26)->nullable()->index();

            // User context — nullable for pre-registration flows
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Destination
            $table->string('destination_type', 20);       // mobile | email
            $table->string('destination_canonical', 320); // normalized E.164 or email
            $table->char('destination_hash', 64)->index(); // SHA-256 of canonical for lookups

            // OTP classification
            $table->string('purpose', 50)->index();
            $table->string('channel', 20);  // sms | email
            $table->string('provider', 50); // log | mim | smtp

            // Security: hashed code only — plaintext NEVER stored
            $table->string('code_hash', 255);

            // Lifecycle
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('send_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);

            // Timestamps
            $table->timestamp('expires_at')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();

            // Safe operational metadata (NO plaintext OTP, NO credentials)
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Compound indexes for performance
            $table->index(['destination_hash', 'purpose', 'status'], 'otp_destination_purpose_status');
            $table->index(['tenant_id', 'purpose', 'status'], 'otp_tenant_purpose_status');
            $table->index(['status', 'expires_at'], 'otp_status_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_records');
    }
};
