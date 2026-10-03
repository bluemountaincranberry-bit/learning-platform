<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('srs_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('srs_card_id')->constrained('srs_cards')->cascadeOnDelete();
            $table->unsignedTinyInteger('grade');
            $table->unsignedInteger('prev_interval')->default(1);
            $table->unsignedInteger('new_interval')->default(1);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('srs_reviews');
    }
};
