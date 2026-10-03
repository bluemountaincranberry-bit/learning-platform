<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lexeme_examples', function (Blueprint $table): void {
            $table->string('translation_language', 8)->nullable()->after('translation');
        });

        Schema::table('grammar_rule_examples', function (Blueprint $table): void {
            $table->string('translation_language', 8)->nullable()->after('translation');
        });
    }

    public function down(): void
    {
        Schema::table('lexeme_examples', function (Blueprint $table): void {
            $table->dropColumn('translation_language');
        });

        Schema::table('grammar_rule_examples', function (Blueprint $table): void {
            $table->dropColumn('translation_language');
        });
    }
};
