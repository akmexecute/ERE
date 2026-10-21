<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Section 73: Every inquiry receives an ID and progresses through New -> Contacted -> Viewing -> Negotiating -> Offer -> Closed / Lost
        Schema::create('inquiries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('inquiry_code', 32)->unique(); // e.g. INQ-2026-0000482
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            // SRS Section 97: website, mobile, facebook, google, telegram, whatsapp, referral, phone
            $table->string('lead_source', 32)->default('website')->index();

            // SRS Section 73: new, contacted, viewing, negotiating, offer, closed, lost
            $table->string('status', 24)->default('new')->index();
            $table->text('initial_message');
            $table->unsignedSmallInteger('unread_buyer_count')->default(0);
            $table->unsignedSmallInteger('unread_seller_count')->default(1);
            $table->timestampTz('last_message_at')->useCurrent()->index();
            $table->timestampsTz();

            $table->index(['seller_id', 'status', 'last_message_at'], 'idx_inquiries_seller_pipeline');
            $table->index(['buyer_id', 'last_message_at'], 'idx_inquiries_buyer_inbox');
        });

        // SRS Section 74: Contextual Messaging (text, photos, documents, booking/property context, read status)
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('message_type', 24)->default('text'); // text, attachment, system_status_change
            $table->text('body');
            $table->timestampTz('read_at')->nullable()->index();
            $table->timestampTz('created_at')->useCurrent()->index();
        });

        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('storage_disk', 32)->default('s3_private');
            $table->string('storage_path', 512);
            $table->string('original_filename', 255);
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('file_size_bytes');
            $table->timestampTz('created_at')->useCurrent();
        });

        // SRS Section 75: Audited Phone Reveal Logs to prevent contact scraping
        Schema::create('phone_reveal_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('viewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_reveal_logs');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('inquiries');
    }
};
