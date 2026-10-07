<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lexemes', function (Blueprint $table): void {
            $table->foreignId('owner_user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('alias_to_lexeme_id')->nullable()->after('owner_user_id')->constrained('lexemes')->restrictOnDelete();
            $table->dropUnique('lexemes_language_normalized_lemma_unique');
        });
        DB::statement('CREATE UNIQUE INDEX lexemes_shared_language_normalized_unique ON lexemes(language, normalized_lemma) WHERE owner_user_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX lexemes_owner_language_normalized_unique ON lexemes(owner_user_id, language, normalized_lemma) WHERE owner_user_id IS NOT NULL');

        Schema::table('user_lexeme_progress', function (Blueprint $table): void {
            $table->dropForeign(['content_lexeme_id']);
            $table->unsignedBigInteger('content_lexeme_id')->nullable()->change();
            $table->foreign('content_lexeme_id')->references('id')->on('content_lexemes')->nullOnDelete();
        });

        Schema::table('srs_cards', function (Blueprint $table): void {
            $table->dropForeign(['content_id']);
            $table->foreignId('lexeme_id')->nullable()->after('user_id')->constrained('lexemes')->restrictOnDelete();
            $table->unsignedBigInteger('content_id')->nullable()->change();
            $table->foreign('content_id')->references('id')->on('contents')->nullOnDelete();
            $table->string('item_key')->nullable()->change();
            $table->timestamp('deactivated_at')->nullable();
            $table->unique(['user_id', 'lexeme_id'], 'srs_cards_user_lexeme_unique');
        });

        Schema::table('user_lexeme_confidences', function (Blueprint $table): void {
            $table->dropForeign(['content_lexeme_id']);
            $table->foreignId('lexeme_id')->nullable()->after('content_lexeme_id')->constrained('lexemes')->restrictOnDelete();
            $table->unsignedBigInteger('content_lexeme_id')->nullable()->change();
            $table->foreign('content_lexeme_id')->references('id')->on('content_lexemes')->nullOnDelete();
            $table->unique(['user_id', 'lexeme_id'], 'user_lexeme_confidences_user_lexeme_unique');
        });

        Schema::create('user_lexeme_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lexeme_id')->constrained('lexemes')->restrictOnDelete();
            $table->string('source_kind', 16);
            $table->foreignId('content_lexeme_id')->nullable()->constrained('content_lexemes')->nullOnDelete();
            $table->foreignId('lesson_lexeme_candidate_id')->nullable()->constrained('lesson_lexeme_candidates')->nullOnDelete();
            $table->text('source_text');
            $table->text('source_example')->nullable();
            $table->string('display_label_snapshot');
            $table->timestamps();

            $table->unique(['user_id', 'content_lexeme_id'], 'user_lexeme_sources_content_unique');
            $table->unique(['user_id', 'lesson_lexeme_candidate_id'], 'user_lexeme_sources_lesson_unique');
            $table->index(['user_id', 'lexeme_id']);
        });
        $sourceKindCheck = "(source_kind = 'content' AND lesson_lexeme_candidate_id IS NULL) OR (source_kind = 'lesson' AND content_lexeme_id IS NULL) OR (source_kind = 'manual' AND content_lexeme_id IS NULL AND lesson_lexeme_candidate_id IS NULL)";
        if (DB::connection()->getDriverName() === 'sqlite') {
            $sqliteSourceKindCheck = "(NEW.source_kind = 'content' AND NEW.lesson_lexeme_candidate_id IS NULL) OR (NEW.source_kind = 'lesson' AND NEW.content_lexeme_id IS NULL) OR (NEW.source_kind = 'manual' AND NEW.content_lexeme_id IS NULL AND NEW.lesson_lexeme_candidate_id IS NULL)";
            DB::unprepared("CREATE TRIGGER user_lexeme_sources_kind_insert BEFORE INSERT ON user_lexeme_sources WHEN NOT ({$sqliteSourceKindCheck}) BEGIN SELECT RAISE(ABORT, 'user_lexeme_sources_kind_reference_check'); END");
            DB::unprepared("CREATE TRIGGER user_lexeme_sources_kind_update BEFORE UPDATE ON user_lexeme_sources WHEN NOT ({$sqliteSourceKindCheck}) BEGIN SELECT RAISE(ABORT, 'user_lexeme_sources_kind_reference_check'); END");
        } else {
            DB::statement("ALTER TABLE user_lexeme_sources ADD CONSTRAINT user_lexeme_sources_kind_reference_check CHECK ({$sourceKindCheck})");
        }
        DB::statement("CREATE UNIQUE INDEX user_lexeme_sources_manual_unique ON user_lexeme_sources(user_id, lexeme_id) WHERE source_kind = 'manual'");
        Schema::create('srs_legacy_migration_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 16)->default('applied');
            $table->string('backup_reference');
            $table->json('payload');
            $table->timestamp('applied_at');
            $table->timestamps();
        });

        Schema::create('personal_lexeme_reconciliation_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('private_lexeme_id');
            $table->unsignedBigInteger('shared_lexeme_id');
            $table->string('status', 16)->default('applied');
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('lexemes')->whereNotNull('alias_to_lexeme_id')->exists()
            || DB::table('personal_lexeme_reconciliation_audits')->where('status', '<>', 'rolled_back')->exists()) {
            throw new RuntimeException('Restore private-to-shared reconciliations from their audit before rolling back this schema.');
        }
        if (DB::table('lexemes')->whereNotNull('owner_user_id')->exists()
            || DB::table('user_lexeme_progress')->whereNull('content_lexeme_id')->exists()
            || DB::table('srs_cards')->whereNotNull('lexeme_id')->exists()
            || DB::table('srs_cards')->whereNull('content_id')->orWhereNull('item_key')->orWhereNotNull('deactivated_at')->exists()
            || DB::table('user_lexeme_confidences')->whereNotNull('lexeme_id')->exists()
            || DB::table('srs_legacy_migration_audits')->where('status', '<>', 'rolled_back')->exists()
            || DB::table('user_lexeme_sources')->exists()) {
            throw new RuntimeException('Restore legacy SRS identity from the migration audit before rolling back this schema.');
        }

        Schema::dropIfExists('srs_legacy_migration_audits');
        Schema::dropIfExists('personal_lexeme_reconciliation_audits');
        DB::unprepared('DROP TRIGGER IF EXISTS user_lexeme_sources_kind_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS user_lexeme_sources_kind_update');
        DB::statement('DROP INDEX IF EXISTS user_lexeme_sources_manual_unique');
        Schema::dropIfExists('user_lexeme_sources');
        Schema::table('user_lexeme_confidences', function (Blueprint $table): void {
            $table->dropUnique('user_lexeme_confidences_user_lexeme_unique');
            $table->dropForeign(['content_lexeme_id']);
            $table->dropConstrainedForeignId('lexeme_id');
            $table->unsignedBigInteger('content_lexeme_id')->nullable(false)->change();
            $table->foreign('content_lexeme_id')->references('id')->on('content_lexemes')->cascadeOnDelete();
        });
        Schema::table('user_lexeme_progress', function (Blueprint $table): void {
            $table->dropForeign(['content_lexeme_id']);
            $table->unsignedBigInteger('content_lexeme_id')->nullable(false)->change();
            $table->foreign('content_lexeme_id')->references('id')->on('content_lexemes')->cascadeOnDelete();
        });
        Schema::table('srs_cards', function (Blueprint $table): void {
            $table->dropUnique('srs_cards_user_lexeme_unique');
            $table->dropConstrainedForeignId('lexeme_id');
            $table->dropForeign(['content_id']);
            $table->dropColumn('deactivated_at');
            $table->string('item_key')->nullable(false)->change();
            $table->unsignedBigInteger('content_id')->nullable(false)->change();
            $table->foreign('content_id')->references('id')->on('contents')->cascadeOnDelete();
        });
        DB::statement('DROP INDEX IF EXISTS lexemes_shared_language_normalized_unique');
        DB::statement('DROP INDEX IF EXISTS lexemes_owner_language_normalized_unique');
        Schema::table('lexemes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('alias_to_lexeme_id');
            $table->dropConstrainedForeignId('owner_user_id');
            $table->unique(['language', 'normalized_lemma']);
        });
    }
};
