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
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36)->index();
            $table->string('type', 255)->index();
            $table->json('payload');
            $table->string('correlation_id', 36)->nullable()->index();
            $table->string('causation_id', 36)->nullable();
            $table->string('actor_id', 36)->nullable();
            
            $table->enum('status', ['pending', 'processing', 'processed', 'failed'])->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->longText('error')->nullable();
            
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
