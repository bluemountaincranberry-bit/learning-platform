<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-model override for `config('ai.pricing')`'s flat prompt/completion
 * price — task: close the "single global price" gap flagged in
 * DatabaseSpanRecorder's docblock (Tier 1 tracing). No row for a model
 * means "use config('ai.pricing') as before" — same DB-override/code-
 * fallback shape as prompt_templates/graph_definitions, just for a price
 * instead of prompt text or graph wiring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_pricing', function (Blueprint $table): void {
            $table->id();
            $table->string('model')->unique();
            $table->decimal('prompt_per_1k_usd', 10, 6);
            $table->decimal('completion_per_1k_usd', 10, 6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_pricing');
    }
};
