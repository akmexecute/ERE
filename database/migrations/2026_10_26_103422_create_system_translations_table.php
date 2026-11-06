<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_translations', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 64)->index();
            $table->string('key', 160)->index();
            $table->string('locale', 8)->index();
            $table->text('value');
            $table->boolean('is_reviewed')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['group', 'key', 'locale'], 'uq_translation_group_key_locale');
            $table->index(['locale', 'group'], 'idx_translations_locale_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_translations');
    }
};
