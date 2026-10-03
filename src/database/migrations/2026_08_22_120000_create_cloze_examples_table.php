<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloze_examples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_lexeme_id')->constrained('content_lexemes')->cascadeOnDelete();
            $table->text('sentence');
            $table->string('target_form');
            $table->text('translation');
            $table->string('target_language', 8);
            $table->string('native_language', 8);
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['content_lexeme_id', 'target_language', 'native_language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloze_examples');
    }
};
