<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_grammar_candidates', function (Blueprint $table): void {
            $table->text('body')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('content_grammar_candidates', function (Blueprint $table): void {
            $table->dropColumn('body');
        });
    }
};
