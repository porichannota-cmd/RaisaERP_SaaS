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
        Schema::create('account_review_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnDelete();
            $table->string('status'); // enum PENDING, APPROVED, REJECTED
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // At most one pending request per user. We include status in the unique key.
            // Since MySQL doesn't support partial unique indexes easily without generated columns in older versions,
            // we will use a composite unique index on user_id and status. This implies only one PENDING, one APPROVED, one REJECTED.
            // Wait, PA said: "At most ONE active/pending account review request per User. Historical approved/rejected requests may remain for traceability."
            // So if we just use a unique index on user_id + status, they can only have ONE rejected request forever! That violates the requirement.
            // Since MariaDB 10.4 doesn't support partial indexes natively (like PostgreSQL `WHERE status='PENDING'`), 
            // the PA requested "Use transaction/locking and DB-safe invariant where portable across: MariaDB, MySQL 8".
            // We will rely on lockForUpdate() during creation in the Service (which is supported).
            // I will NOT add a unique index here because MariaDB doesn't cleanly support partial unique indexes on status='PENDING'.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_review_requests');
    }
};
