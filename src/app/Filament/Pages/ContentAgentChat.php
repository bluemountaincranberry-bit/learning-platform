<?php

namespace App\Filament\Pages;

use App\Modules\Ai\Interfaces\Jobs\RunAgentTurnJob;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Domain\Models\AgentMessage;
use App\Modules\Ai\Application\Agent\ContentAgentService;
use App\Support\AiConfig;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ContentAgentChat extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Lesson Agent';

    protected string $view = 'filament.pages.content-agent-chat';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?int $conversationId = null;

    public static function shouldRegisterNavigation(): bool
    {
        return AiConfig::isAgentEnabled();
    }

    public function mount(): void
    {
        if (! AiConfig::isAgentEnabled()) {
            abort(404);
        }

        $conversation = AgentConversation::query()->firstOrCreate(
            [
                'created_by' => Auth::id(),
                'status' => AgentConversation::STATUS_ACTIVE,
                'agent_type' => ContentAgentService::AGENT_TYPE,
            ],
            []
        );

        $this->conversationId = $conversation->id;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('message')
                    ->label('Message')
                    ->placeholder('Describe the lesson you want, e.g. "Make a B1 lesson from this PDF"')
                    ->rows(3)
                    ->columnSpanFull(),
                FileUpload::make('attachment')
                    ->label('Attach PDF')
                    ->disk('local')
                    ->directory('agent-uploads')
                    ->acceptedFileTypes(['application/pdf'])
                    ->preserveFilenames()
                    ->maxSize((int) config('ai.agent.max_upload_kb', 10240))
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /**
     * @return Collection<int, AgentMessage>
     */
    public function getMessagesProperty(): Collection
    {
        return AgentConversation::query()
            ->find($this->conversationId)
            ?->messages()
            ->where('role', '!=', AgentMessage::ROLE_TOOL)
            ->orderBy('created_at')
            ->get() ?? collect();
    }

    /**
     * True while the last message is from the admin and the agent hasn't
     * replied yet — drives the "thinking…" indicator and polling.
     */
    public function getIsWaitingProperty(): bool
    {
        $last = $this->messages->last();

        return $last !== null && $last->role === AgentMessage::ROLE_USER;
    }

    public function send(): void
    {
        $state = $this->form->getState();
        $message = trim((string) ($state['message'] ?? ''));
        $attachmentPath = $state['attachment'] ?? null;

        if ($message === '' && $attachmentPath === null) {
            return;
        }

        $limit = (int) config('ai.agent.turns_per_day', 0);
        if ($limit > 0) {
            $key = 'ai:agent:rate_limit:'.Auth::id().':'.now()->format('Y-m-d');
            $count = (int) Cache::get($key, 0);

            if ($count >= $limit) {
                Notification::make()
                    ->title('Daily limit reached')
                    ->body('You have used the lesson agent too many times today. Try again tomorrow.')
                    ->danger()
                    ->send();

                return;
            }

            Cache::put($key, $count + 1, now()->endOfDay()->addSecond());
        }

        $conversation = AgentConversation::query()->findOrFail($this->conversationId);

        $conversation->messages()->create([
            'role' => AgentMessage::ROLE_USER,
            'content' => $message !== '' ? $message : null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentPath !== null ? basename((string) $attachmentPath) : null,
        ]);

        RunAgentTurnJob::dispatch($conversation->id);

        $this->form->fill();
    }
}
