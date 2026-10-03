<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_check_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->string('operation_id', 80);
            $table->json('result');
            $table->timestamps();
            $table->unique(['user_id', 'operation_id']);
            $table->index(['user_id', 'content_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_check_submissions');
    }
};
