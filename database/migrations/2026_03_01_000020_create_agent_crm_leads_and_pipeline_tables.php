<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Sections 96, 97, 98: Agent & Developer CRM Leads Pipeline
        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('lead_code', 36)->unique(); // e.g. LEAD-2026-000512
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('company_branches')->nullOnDelete();
            $table->foreignId('agent_id')->constrained('users');
            $table->foreignId('assigned_agent_id')->constrained('users');
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('inquiry_id')->nullable()->constrained('inquiries')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();

            // Client Contact Info
            $table->string('client_name', 140);
            $table->string('client_phone', 24)->index();
            $table->string('client_email', 160)->nullable();

            // SRS Section 97: walk_in, website, portal_referral, social_media, telegram, whatsapp, facebook, google, referral, phone, advertisement, agent
            $table->string('lead_source', 32)->default('website')->index();

            // SRS Section 98: new_lead, contacted, qualified, viewing, negotiation, offer, closed, lost
            $table->string('stage', 28)->default('new_lead')->index();
            $table->string('pipeline_stage', 28)->default('new_lead')->index();

            $table->decimal('budget_min_etb', 18, 2)->nullable();
            $table->decimal('budget_max_etb', 18, 2)->nullable();
            $table->string('preferred_location_note', 180)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('last_contacted_at')->nullable();
            $table->timestampTz('next_follow_up_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['assigned_agent_id', 'stage', 'lead_source'], 'idx_crm_leads_agent_board');
        });

        // Tracks timestamps of stage movements to calculate pipeline velocity for Section 96 CRM Dashboard
        Schema::create('crm_stage_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_stage', 28)->nullable();
            $table->string('to_stage', 28)->index();
            $table->unsignedInteger('duration_in_stage_seconds')->default(0);
            $table->text('notes')->nullable();
            $table->timestampTz('transitioned_at')->useCurrent()->index();
            $table->timestampsTz();
        });

        // Activities log (calls, telegram, whatsapp, viewing_scheduled, offer_logged, etc.)
        Schema::create('crm_lead_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users');
            // call, telegram, whatsapp, viewing_scheduled, offer_logged, stage_changed, note
            $table->string('activity_type', 36)->index();
            $table->string('from_stage', 28)->nullable();
            $table->string('to_stage', 28)->nullable();
            $table->text('summary');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_activities');
        Schema::dropIfExists('crm_stage_history');
        Schema::dropIfExists('crm_leads');
    }
};
