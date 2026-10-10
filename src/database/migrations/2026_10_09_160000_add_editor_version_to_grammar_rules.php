<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_rules', function (Blueprint $table): void {
            $table->unsignedInteger('editor_version')->default(0);
        });

        Schema::table('grammar_rule_examples', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('grammar_rules', function (Blueprint $table): void {
            $table->dropColumn('editor_version');
        });

        Schema::table('grammar_rule_examples', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};
