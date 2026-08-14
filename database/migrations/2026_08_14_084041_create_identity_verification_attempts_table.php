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
        Schema::create('identity_verification_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_identity_verification_id')->constrained('user_identity_verifications')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('provider');
            $table->string('operation'); // 'extract' or 'verify'
            $table->string('status');
            $table->string('provider_reference')->nullable();
            $table->string('failure_code')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_verification_attempts');
    }
};
