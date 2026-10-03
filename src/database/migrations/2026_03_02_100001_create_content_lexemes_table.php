<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_lexemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->string('type', 16)->default('word'); // word | phrase
            $table->string('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('frequency')->nullable();
            $table->timestamps();

            $table->index(['content_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_lexemes');
    }
};
