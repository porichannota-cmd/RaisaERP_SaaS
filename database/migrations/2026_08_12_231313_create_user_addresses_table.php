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
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // PRESENT, PERMANENT, OFFICE, etc.

            $table->string('country')->default('Bangladesh');
            $table->string('division')->nullable();
            $table->string('district')->nullable();
            $table->string('upazila_thana')->nullable();
            $table->string('union')->nullable();
            $table->string('ward')->nullable();
            $table->string('post_office')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('village_area')->nullable();
            $table->string('road_street')->nullable();
            $table->string('house_building')->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();

            $table->timestamps();

            // Allow multiple types, but ensure a user has only one of each type if necessary.
            // A unique index might be too restrictive if they delete/re-add, but we enforce it in service.
            $table->unique(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
