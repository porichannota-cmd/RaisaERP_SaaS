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
        Schema::create('authorization_grants', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('role_id', 26);
            $table->string('permission_key');
            $table->string('scope_type', 50);
            $table->char('scope_id', 26)->nullable();
            $table->json('constraints')->nullable();

            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_key')->references('key')->on('permissions')->onDelete('cascade');

            // Critical invariant: atomic grant
            $table->unique(['role_id', 'permission_key', 'scope_type', 'scope_id'], 'unique_grant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorization_grants');
    }
};
