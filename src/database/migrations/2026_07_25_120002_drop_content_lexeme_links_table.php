<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('content_lexeme_links');
    }

    public function down(): void
    {
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
};
