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
        Schema::create('registration_identity_documents', function (Blueprint $table) {
            $table->char('id', 26)->primary();

            // FK to the registration session that owns this document.
            // Using char(26) for ULID.
            $table->char('registration_session_id', 26)->index();

            // The kind of document (e.g., 'nid_front', 'profile_photo').
            $table->string('kind', 30);

            // Storage details for the staging area.
            $table->string('storage_disk', 50);
            $table->string('storage_path', 500);
            $table->string('original_filename_safe', 255);
            $table->string('detected_mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64)->nullable();

            // Optional image metadata.
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();

            // Lifecycle status (e.g., 'pending', 'validated', 'claimed').
            $table->string('status', 30);

            // Expiration timestamp based on session TTL.
            $table->timestamp('expires_at')->nullable()->index();

            // After successful account creation, these are populated.
            // Note: users table uses unsigned big int for id.
            $table->unsignedBigInteger('claimed_by_user_id')->nullable()->index();
            $table->timestamp('claimed_at')->nullable();

            // Extensible metadata.
            $table->json('metadata')->nullable();

            $table->timestamps();

            // FK to registration_sessions
            $table->foreign('registration_session_id')
                ->references('id')
                ->on('registration_sessions')
                ->onDelete('cascade'); // if a session is hard-deleted, staging docs go with it

            // Note: not setting a strict FK constraint on claimed_by_user_id yet
            // to allow flexibility during the claim transaction, but it conceptually
            // points to users.id.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_identity_documents');
    }
};
