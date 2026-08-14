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
        Schema::create('user_identity_verifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('document_type'); // e.g., 'nid'
            $table->string('status'); // e.g., 'EXTRACTION_PENDING'
            $table->string('provider'); // Active provider, e.g., 'null'
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->boolean('manual_review_required')->default(false);
            $table->text('extracted_data_encrypted')->nullable();
            $table->string('normalized_name')->nullable();
            $table->date('normalized_dob')->nullable();
            $table->text('nid_number_encrypted')->nullable();
            $table->string('nid_number_fingerprint', 64)->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('failure_code')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Ensure only one verification record per user
            $table->unique('user_id');
            // If the policy allows duplicate NIDs per user (highly unlikely for identity verif),
            // but we must not enforce global NID uniqueness before explicit policy.
            // "If requirement is one NID per user globally, use fingerprint-based unique constraint only if PA-approved" ->
            // "If policy is not fully frozen: document and stop before enforcing global uniqueness."
            // We will NOT enforce global uniqueness on nid_number_fingerprint yet.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_identity_verifications');
    }
};
