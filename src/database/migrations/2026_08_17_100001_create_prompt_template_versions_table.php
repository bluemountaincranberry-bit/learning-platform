<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only version history for one `prompt_templates` row — same "log
 * every version, never mutate a published one" shape as
 * grammar_exam_attempts/content_exam_attempts elsewhere in this codebase.
 * `system_template`/`user_template` hold `{{variable}}` placeholders,
 * substituted by PromptTemplateRenderer at resolve time. `model` is an
 * optional per-prompt override (null = caller's own default model, e.g.
 * OpenAiClient's hardcoded gpt-4o-mini) — kept here rather than only on
 * prompt_templates so a rollback to an older version also rolls back
 * which model it was tuned for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prompt_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('system_template');
            $table->text('user_template');
            $table->string('model')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['prompt_template_id', 'version']);
        });

        Schema::table('prompt_templates', function (Blueprint $table): void {
            $table->foreign('active_version_id')->references('id')->on('prompt_template_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prompt_templates', function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
        });

        Schema::dropIfExists('prompt_template_versions');
    }
};
