<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            $table->text('example_translation')->nullable()->after('example');
            $table->string('level', 4)->nullable()->after('type');
            $table->unsignedInteger('frequency')->nullable()->after('level');
        });

        Schema::table('content_grammar_candidates', function (Blueprint $table): void {
            $table->text('example_translation')->nullable()->after('example');
        });
    }

    public function down(): void
    {
        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            $table->dropColumn(['example_translation', 'level', 'frequency']);
        });

        Schema::table('content_grammar_candidates', function (Blueprint $table): void {
            $table->dropColumn('example_translation');
        });
    }
};
