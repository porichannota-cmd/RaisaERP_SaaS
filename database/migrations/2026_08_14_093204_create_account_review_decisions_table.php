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
        Schema::create('account_review_decisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_review_request_id')->constrained('account_review_requests', 'id', 'fk_acc_rev_decisions_req_id')->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\User::class, 'reviewer_id')->constrained('users', 'id', 'fk_acc_rev_decisions_rev_id')->cascadeOnDelete();
            $table->string('decision'); // enum APPROVE, REJECT
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_review_decisions');
    }
};
