<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table): void {
            // Discriminator for which AgentService (config('ai.agent.registry'))
            // owns this conversation — content_authoring | student_tutor.
            // Defaulted to the only agent that exists today so existing rows
            // stay valid without a backfill step.
            $table->string('agent_type', 32)->default('content_authoring')->after('created_by');
            $table->index('agent_type');
        });
    }

    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table): void {
            $table->dropIndex(['agent_type']);
            $table->dropColumn('agent_type');
        });
    }
};
