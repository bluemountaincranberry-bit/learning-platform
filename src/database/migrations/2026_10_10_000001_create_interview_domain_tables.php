<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('interview_topics')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'parent_id', 'sort_order']);
        });

        Schema::create('interview_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('interview_topics')->nullOnDelete();
            $table->text('prompt_en');
            $table->text('prompt_ru')->nullable();
            $table->string('preparation_state')->default('unpracticed');
            $table->timestamps();
            $table->index(['user_id', 'preparation_state']);
        });

        Schema::create('interview_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('interview_question_tag', function (Blueprint $table): void {
            $table->foreignId('question_id')->constrained('interview_questions')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('interview_tags')->cascadeOnDelete();
            $table->primary(['question_id', 'tag_id']);
        });

        Schema::create('interview_answer_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained('interview_questions')->cascadeOnDelete();
            $table->string('kind');
            $table->text('text_en')->nullable();
            $table->text('text_ru')->nullable();
            $table->timestamps();
            $table->unique(['question_id', 'kind']);
        });

        Schema::create('interview_answer_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('answer_variant_id')->constrained('interview_answer_variants')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('text_en')->nullable();
            $table->text('text_ru')->nullable();
            $table->timestamps();
            $table->index(['answer_variant_id', 'created_at']);
        });

        Schema::create('interview_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('career_goal')->nullable();
            $table->json('skills')->nullable();
            $table->string('experience_level')->nullable();
            $table->json('projects')->nullable();
            $table->json('experience_stories')->nullable();
            $table->timestamps();
        });

        Schema::create('interview_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('interview_profiles')->cascadeOnDelete();
            $table->string('title');
            $table->date('target_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_milestones');
        Schema::dropIfExists('interview_profiles');
        Schema::dropIfExists('interview_answer_revisions');
        Schema::dropIfExists('interview_answer_variants');
        Schema::dropIfExists('interview_question_tag');
        Schema::dropIfExists('interview_tags');
        Schema::dropIfExists('interview_questions');
        Schema::dropIfExists('interview_topics');
    }
};
