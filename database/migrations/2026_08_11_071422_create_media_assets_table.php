<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            $table->unsignedBigInteger('uploaded_by');

            $table->string('original_filename');
            $table->string('storage_disk');
            $table->string('storage_path')->unique();

            $table->string('mime_type');
            $table->string('extension');
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64)->nullable()->index();

            $table->string('media_kind');
            $table->string('visibility');
            $table->string('processing_status');
            $table->string('security_status');

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('uploaded_by')->references('id')->on('users');
            // Assuming tenant_id is ulid, but typically we add foreign keys explicitly if there's a tenants table.
            // In Wave 1A, Tenant is char(26). We will skip strict foreign key here for now if tenants table is managed differently, or we add it:
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
