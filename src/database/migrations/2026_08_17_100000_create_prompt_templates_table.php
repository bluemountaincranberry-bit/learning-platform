<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stable, admin-facing pointer to a prompt: `key` is what application code
 * looks up (e.g. `ai_explain_lexeme`, matching the call site's existing
 * hardcoded prompt), `active_version_id` is the currently published
 * version — null means "no override exists yet, callers keep using their
 * PHP-coded default" (PromptRegistryService's fallback path). Editing
 * always creates a new `prompt_template_versions` row; this table's
 * `active_version_id` only changes on an explicit publish action, never
 * as a side effect of saving a draft — see PromptRegistryService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // FK to prompt_template_versions added after that table exists
            // (migration below) to avoid a forward reference; nullable by
            // design (see class docblock).
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
