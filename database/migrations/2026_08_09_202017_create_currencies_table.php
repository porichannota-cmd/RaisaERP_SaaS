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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique()->comment('ISO 4217 code (e.g. BDT)');
            $table->char('numeric_code', 3)->unique();
            $table->string('name');
            $table->string('symbol');
            $table->unsignedTinyInteger('minor_unit_exp')->default(2)->comment('Scale for bcmath operations');
            $table->enum('rounding_method', ['HALF_UP', 'HALF_EVEN', 'CEILING', 'FLOOR'])->default('HALF_UP');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
