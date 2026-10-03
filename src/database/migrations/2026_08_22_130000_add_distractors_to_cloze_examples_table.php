<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloze_examples', function (Blueprint $table): void {
            $table->json('distractors')->nullable()->after('translation');
        });
    }

    public function down(): void
    {
        Schema::table('cloze_examples', function (Blueprint $table): void {
            $table->dropColumn('distractors');
        });
    }
};
