<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_grammar_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grammar_rule_id')->constrained('grammar_rules')->cascadeOnDelete();
            $table->string('status')->default('learning');
            $table->timestamp('started_at');
            $table->timestamp('learned_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'grammar_rule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_grammar_rules');
    }
};
