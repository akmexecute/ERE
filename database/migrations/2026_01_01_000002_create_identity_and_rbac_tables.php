<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 140);
            $table->string('phone', 20)->unique(); // Normalized E.164: +2519... or +2517...
            $table->string('email', 160)->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('profile_photo_path', 512)->nullable();
            $table->string('preferred_locale', 5)->default('en');
            // SRS Section 75: show_phone, hide_phone, messaging_only
            $table->string('phone_privacy', 24)->default('show_phone');
            // SRS Section 22: Verification Level 0..5
            $table->unsignedTinyInteger('verification_level')->default(0)->index();
            $table->string('status', 24)->default('active')->index(); // active, suspended, banned
            $table->timestampTz('phone_verified_at')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_en', 100);
            $table->string('name_am', 100)->nullable();
            $table->string('name_om', 100)->nullable();
            $table->boolean('is_staff_role')->default(false)->index();
            $table->timestampsTz();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique(); // e.g. property.approve, verification.review
            $table->string('module', 40)->index();
            $table->string('description', 255)->nullable();
            $table->timestampsTz();
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestampTz('assigned_at')->useCurrent();
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['user_id', 'permission_id']);
        });

        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('purpose', 32)->default('login'); // login, register, phone_change
            $table->string('code_hash', 128);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->ipAddress('requested_ip')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('login_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone_or_email', 160)->index();
            $table->string('auth_method', 32); // otp, password, 2fa
            $table->boolean('succeeded')->default(false)->index();
            $table->boolean('is_suspicious')->default(false)->index();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        // Phase 1 Company & Branch stub for Seller/Company Verification (SRS Section 25 & 100)
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_user_id')->constrained('users');
            $table->string('legal_name', 180);
            $table->string('trade_name', 180)->nullable();
            $table->string('company_type', 40)->default('agency'); // agency, developer, property_management
            $table->string('tin_number', 40)->nullable()->unique();
            $table->string('trade_registration_number', 64)->nullable()->unique();
            $table->string('license_number', 64)->nullable();
            $table->unsignedTinyInteger('verification_level')->default(0)->index();
            $table->string('status', 24)->default('pending');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
        Schema::dropIfExists('login_histories');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
};
