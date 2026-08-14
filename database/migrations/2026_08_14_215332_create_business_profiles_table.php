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
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            $table->foreignId('owner_user_id')->constrained('users');

            $table->char('tenant_id', 26)->nullable()->unique();
            $table->foreign('tenant_id')->references('id')->on('tenants');

            $table->string('legal_name');
            $table->string('display_name')->nullable();

            $table->text('trade_license_encrypted')->nullable();
            $table->string('trade_license_fingerprint', 64)->nullable()->index('idx_bp_tl_fingerprint');

            $table->text('tin_encrypted')->nullable();
            $table->string('tin_fingerprint', 64)->nullable()->index('idx_bp_tin_fingerprint');

            $table->text('bin_encrypted')->nullable();
            $table->string('bin_fingerprint', 64)->nullable()->index('idx_bp_bin_fingerprint');

            $table->enum('provisioning_status', ['DRAFT', 'READY_FOR_PROVISIONING', 'PROVISIONED'])
                  ->default('DRAFT')
                  ->index('idx_bp_prov_status');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
