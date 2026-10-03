<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 6.1 (design doc "Дизайн-решение 6.1"): purely observational columns,
 * the same role `tool_args`/`tool_result` already play on this table — an
 * admin looking at a trace/message later can see which page the student was
 * on when they asked ("Обсуждаем: <content title>"). Nothing in the running
 * agent reads these columns back into a prompt; the one-time context text is
 * folded directly into the user message's own `content` instead (see
 * ChatPage.vue/TutorConversationController::storeMessage()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_messages', function (Blueprint $table): void {
            // 'content' | 'grammar' | 'lexeme' — matches AskAiButton.vue's context.type.
            $table->string('context_type', 16)->nullable()->after('latency_ms');
            $table->unsignedBigInteger('context_ref_id')->nullable()->after('context_type');
            $table->string('context_label')->nullable()->after('context_ref_id');
        });
    }

    public function down(): void
    {
        Schema::table('agent_messages', function (Blueprint $table): void {
            $table->dropColumn(['context_type', 'context_ref_id', 'context_label']);
        });
    }
};
