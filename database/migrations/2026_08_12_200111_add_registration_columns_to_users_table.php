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
        Schema::table('users', function (Blueprint $table) {
            // PA-01: Change email to nullable
            $table->string('email')->nullable()->change();

            // Additive identity columns
            $table->string('mobile_canonical', 20)->nullable()->unique()->after('email');
            $table->timestamp('mobile_verified_at')->nullable()->after('mobile_canonical');
            $table->string('enterprise_user_id', 20)->nullable()->unique()->after('mobile_verified_at');
            $table->string('account_status', 30)->default('mobile_verified')->after('enterprise_user_id');
            $table->string('registration_source', 30)->nullable()->after('account_status');
            $table->boolean('two_factor_enabled')->default(false)->after('registration_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert email to NOT NULL (this might fail if any user has null email,
            // but for rollback of development migrations it's standard).
            $table->string('email')->nullable(false)->change();

            $table->dropColumn([
                'mobile_canonical',
                'mobile_verified_at',
                'enterprise_user_id',
                'account_status',
                'registration_source',
                'two_factor_enabled',
            ]);
        });
    }
};
