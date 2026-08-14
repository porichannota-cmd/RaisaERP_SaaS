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
        Schema::create('account_status_history', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignIdFor(\App\Models\User::class)->constrained('users', 'id', 'fk_acc_status_hist_user_id')->cascadeOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->foreignIdFor(\App\Models\User::class, 'actor_id')->nullable()->constrained('users', 'id', 'fk_acc_status_hist_actor_id')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_status_history');
    }
};
