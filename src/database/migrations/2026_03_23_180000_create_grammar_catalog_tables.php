<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grammar_topics', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('language', 8)->default('en');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['language', 'status']);
        });

        Schema::create('grammar_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topic_id')->constrained('grammar_topics')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('language', 8)->default('en');
            $table->string('title');
            $table->string('status', 32)->default('draft');
            $table->string('level', 4)->nullable();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['topic_id', 'status']);
            $table->index(['language', 'level']);
        });

        Schema::create('lexemes', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('language', 8)->default('en');
            $table->string('lemma');
            $table->string('normalized_lemma')->index();
            $table->string('part_of_speech', 32)->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('level', 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['language', 'status']);
            $table->unique(['language', 'normalized_lemma']);
        });

        Schema::create('grammar_rule_lexeme', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grammar_rule_id')->constrained('grammar_rules')->cascadeOnDelete();
            $table->foreignId('lexeme_id')->constrained('lexemes')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['grammar_rule_id', 'lexeme_id']);
        });

        Schema::create('grammar_rule_examples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grammar_rule_id')->constrained('grammar_rules')->cascadeOnDelete();
            $table->string('language', 8)->default('en');
            $table->text('example');
            $table->text('translation')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['grammar_rule_id', 'is_primary']);
        });

        Schema::create('lexeme_examples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained('lexemes')->cascadeOnDelete();
            $table->string('language', 8)->default('en');
            $table->text('example');
            $table->text('translation')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['lexeme_id', 'is_primary']);
        });

        Schema::create('lexeme_associations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained('lexemes')->cascadeOnDelete();
            $table->foreignId('related_lexeme_id')->constrained('lexemes')->cascadeOnDelete();
            $table->string('type', 32)->default('related');
            $table->text('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['lexeme_id', 'related_lexeme_id', 'type'], 'lexeme_associations_unique');
        });

        Schema::create('content_rule_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('grammar_rule_id')->constrained('grammar_rules')->cascadeOnDelete();
            $table->string('status', 32)->default('linked');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['content_id', 'grammar_rule_id']);
            $table->index(['grammar_rule_id', 'status']);
        });

        Schema::create('content_lexeme_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('lexeme_id')->constrained('lexemes')->cascadeOnDelete();
            $table->foreignId('content_lexeme_id')->nullable()->constrained('content_lexemes')->nullOnDelete();
            $table->string('status', 32)->default('linked');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['content_id', 'lexeme_id']);
            $table->index(['lexeme_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_lexeme_links');
        Schema::dropIfExists('content_rule_links');
        Schema::dropIfExists('lexeme_associations');
        Schema::dropIfExists('lexeme_examples');
        Schema::dropIfExists('grammar_rule_examples');
        Schema::dropIfExists('grammar_rule_lexeme');
        Schema::dropIfExists('lexemes');
        Schema::dropIfExists('grammar_rules');
        Schema::dropIfExists('grammar_topics');
    }
};
