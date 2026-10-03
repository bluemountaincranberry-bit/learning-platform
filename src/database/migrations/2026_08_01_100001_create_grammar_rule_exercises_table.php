<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grammar_rule_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grammar_rule_id')->constrained('grammar_rules')->cascadeOnDelete();
            $table->string('type');
            $table->text('prompt');
            $table->string('answer')->nullable();
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('answer_index')->nullable();
            $table->text('explanation')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grammar_rule_exercises');
    }
};
