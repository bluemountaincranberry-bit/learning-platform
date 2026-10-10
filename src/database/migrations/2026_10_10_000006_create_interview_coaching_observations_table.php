<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_coaching_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('interview_profiles')->cascadeOnDelete();
            $table->string('pattern_type', 24);
            $table->string('summary', 500);
            $table->json('examples');
            $table->string('source_key', 64);
            $table->timestamps();
            $table->unique(['profile_id', 'source_key']);
            $table->index(['profile_id', 'pattern_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_coaching_observations');
    }
};
