<?php

use App\Filament\Pages\ContentAgentChat;
use App\Modules\Ai\Interfaces\Jobs\RunAgentTurnJob;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Task 1.8 guardrail: rate limiting must sit at the boundary (this
 * Filament page's send() method, before it ever creates the user
 * AgentMessage or dispatches RunAgentTurnJob) — RunAgentTurnJob is a job,
 * not an HTTP route, so the existing AiRateLimit HTTP middleware does not
 * cover it. See docs/architecture/agent-framework-roadmap.md, step 5.9.
 */
function actingAdminForAgentChat(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-agent-chat@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }

    test()->actingAs($admin, 'web');

    return $admin;
}

beforeEach(function () {
    config(['ai.agent.enabled' => true, 'ai.provider' => 'openai']);
});

test('send() dispatches the turn job and stores the user message under the daily limit', function () {
    Queue::fake();
    $admin = actingAdminForAgentChat();
    config(['ai.agent.turns_per_day' => 5]);

    Livewire::test(ContentAgentChat::class)
        ->fillForm(['message' => 'Hello agent'])
        ->call('send');

    $conversation = AgentConversation::query()->where('created_by', $admin->id)->firstOrFail();
    expect($conversation->messages()->where('role', 'user')->count())->toBe(1);

    Queue::assertPushed(RunAgentTurnJob::class);
});

test('send() does not dispatch the turn job or store a message once the daily limit is reached', function () {
    Queue::fake();
    $admin = actingAdminForAgentChat();
    config(['ai.agent.turns_per_day' => 1]);

    Livewire::test(ContentAgentChat::class)->fillForm(['message' => 'First message'])->call('send');
    Queue::assertPushed(RunAgentTurnJob::class, 1);

    Livewire::test(ContentAgentChat::class)->fillForm(['message' => 'Second message, over the limit'])->call('send');

    $conversation = AgentConversation::query()->where('created_by', $admin->id)->firstOrFail();
    expect($conversation->messages()->where('role', 'user')->count())->toBe(1)
        ->and($conversation->messages()->where('content', 'Second message, over the limit')->exists())->toBeFalse();

    Queue::assertPushed(RunAgentTurnJob::class, 1);
});

test('turns_per_day = 0 disables the limit entirely', function () {
    Queue::fake();
    actingAdminForAgentChat();
    config(['ai.agent.turns_per_day' => 0]);

    for ($i = 0; $i < 3; $i++) {
        Livewire::test(ContentAgentChat::class)->fillForm(['message' => "Message {$i}"])->call('send');
    }

    Queue::assertPushed(RunAgentTurnJob::class, 3);
});
