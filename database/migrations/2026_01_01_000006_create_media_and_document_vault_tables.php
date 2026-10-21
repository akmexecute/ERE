<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('original_sanitized_path', 512);
            $table->string('webp_thumb_path', 512)->nullable();
            $table->string('webp_medium_path', 512)->nullable();
            $table->string('webp_large_path', 512)->nullable();
            $table->string('avif_medium_path', 512)->nullable();
            // exterior, living_room, bedroom, kitchen, bathroom, compound, site_plan (SRS Section 202)
            $table->string('room_tag', 40)->default('exterior')->index();
            $table->string('caption', 200)->nullable();
            // 64-bit Perceptual Hash (dHash) for duplicate photo detection in Module 08 (SRS Section 31 & 32)
            $table->string('perceptual_hash', 16)->nullable()->index();
            $table->boolean('exif_stripped')->default(false);
            $table->boolean('watermarked')->default(false);
            $table->boolean('is_cover')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['property_id', 'sort_order'], 'idx_property_images_order');
        });

        Schema::create('property_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            // walkthrough, drone, development_progress, project_presentation (SRS Section 20)
            $table->string('video_type', 40)->default('walkthrough');
            $table->string('storage_disk', 32)->default('public');
            $table->string('video_path_or_url', 512);
            $table->string('thumbnail_path', 512)->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->timestampsTz();
        });

        // Secure Document Vault with Access Control & Versioning (SRS Sections 27, 28, 29, 144)
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('documentable_type', 140); // Property, User, Company, Listing
            $table->unsignedBigInteger('documentable_id');
            $table->foreignId('uploaded_by')->constrained('users');
            // ownership_deed, lease_contract, building_permit, site_plan, tax_clearance, national_id, trade_license
            $table->string('document_type_code', 48)->index();
            $table->string('title', 180);
            // SRS Section 28: public, private, verification_only, admin_only, transaction_participants
            $table->string('access_level', 32)->default('verification_only')->index();
            $table->unsignedSmallInteger('current_version_number')->default(1);
            $table->string('verification_status', 24)->default('pending'); // pending, verified, rejected
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['documentable_type', 'documentable_id'], 'idx_documents_morph');
        });

        // Document Version History: v1 -> v2 -> v3 (SRS Section 29)
        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->string('storage_disk', 32)->default('s3_private');
            $table->string('storage_path', 512);
            $table->string('original_filename', 255);
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('sha256_checksum', 64)->index();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('change_notes', 300)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['document_id', 'version_number'], 'uq_document_version_num');
        });

        // Immutable Download & Signed-URL Audit Trail (SRS Section 144)
        Schema::create('document_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action', 32); // signed_url_issued, downloaded, previewed
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access_logs');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('property_videos');
        Schema::dropIfExists('property_images');
    }
};
