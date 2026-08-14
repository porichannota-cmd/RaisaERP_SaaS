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
        Schema::create('user_consents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('consent_type'); // TERMS_OF_SERVICE, PRIVACY_POLICY, MARKETING
            $table->string('document_version');
            $table->string('document_hash')->nullable();

            $table->timestamp('accepted_at');
            $table->timestamp('revoked_at')->nullable();

            $table->string('source')->nullable(); // web, mobile, etc.
            $table->string('ip_fingerprint')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'consent_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
