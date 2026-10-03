<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->string('title');
            $table->string('language', 8)->default('en');
            $table->string('level', 4)->nullable();
            $table->string('origin')->default('curated');
            $table->string('status')->default('draft');
            $table->string('source_url')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderation_comment')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'origin']);
            $table->index(['type', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
