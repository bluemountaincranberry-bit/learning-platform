<?php

use App\Modules\Content\Domain\ContentSourceKey;
use App\Modules\Content\Domain\GrammarRuleTitle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VIK-16: identity keys for catalog dedup.
 *
 * - grammar_rules.normalized_title: the rule's title identity
 *   (GrammarRuleTitle::normalize), so analysis can link an existing rule by
 *   title before falling back to embeddings.
 * - contents.source_key: the source identity ("youtube:<video id>",
 *   ContentSourceKey), so the same video submitted under another URL form
 *   is recognised.
 *
 * Both are indexed but not unique yet: the dev catalog still holds
 * duplicates until `catalog:merge-duplicates --apply` is run by a human.
 * Existing rows are backfilled; nothing is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_rules', function (Blueprint $table): void {
            $table->string('normalized_title')->nullable()->after('title');
            $table->index(['language', 'normalized_title']);
        });

        Schema::table('contents', function (Blueprint $table): void {
            $table->string('source_key', 191)->nullable()->after('source_url');
            $table->index('source_key');
        });

        DB::table('grammar_rules')->orderBy('id')->select(['id', 'title'])->each(function (object $rule): void {
            DB::table('grammar_rules')->where('id', $rule->id)
                ->update(['normalized_title' => GrammarRuleTitle::normalize((string) $rule->title)]);
        });

        DB::table('contents')->whereNotNull('source_url')->orderBy('id')->select(['id', 'type', 'source_url'])->each(function (object $content): void {
            DB::table('contents')->where('id', $content->id)
                ->update(['source_key' => ContentSourceKey::for((string) $content->type, $content->source_url)]);
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table): void {
            $table->dropIndex(['source_key']);
            $table->dropColumn('source_key');
        });

        Schema::table('grammar_rules', function (Blueprint $table): void {
            $table->dropIndex(['language', 'normalized_title']);
            $table->dropColumn('normalized_title');
        });
    }
};
