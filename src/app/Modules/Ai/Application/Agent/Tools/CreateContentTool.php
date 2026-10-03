<?php

namespace App\Modules\Ai\Application\Agent\Tools;

use App\Exceptions\AgentToolException;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Content\Application\Contracts\DraftContentCreatorInterface;

/**
 * Creates a draft lesson (Content row) from text the agent gathered in this
 * conversation (typed by the admin and/or extracted from a PDF). Always
 * lands in `draft` status — publishing/applying candidates stays a manual
 * admin action in the existing Content review UI, this tool never does it.
 */
class CreateContentTool implements AgentTool
{
    public function __construct(
        private readonly DraftContentCreatorInterface $contentDrafts
    ) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'create_content',
            description: 'Creates a new draft lesson with the given text so it can be analyzed for vocabulary and grammar. Use this once you know the title, type, language and have the text to learn from.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'type' => ['type' => 'string', 'enum' => $this->contentDrafts->types()],
                    'language' => ['type' => 'string', 'description' => 'ISO 639-1 code of the language being learned, e.g. "en".'],
                    'level' => ['type' => 'string', 'enum' => $this->contentDrafts->levels(), 'description' => 'Optional CEFR level if known.'],
                    'source_text' => ['type' => 'string', 'description' => 'The vocabulary/phrases/grammar text this lesson is built from.'],
                ],
                'required' => ['title', 'type', 'language', 'source_text'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $title = trim((string) ($arguments['title'] ?? ''));
        $type = (string) ($arguments['type'] ?? '');
        $language = trim((string) ($arguments['language'] ?? ''));
        $sourceText = trim((string) ($arguments['source_text'] ?? ''));
        $level = $arguments['level'] ?? null;

        if ($title === '') {
            throw new AgentToolException('title is required.');
        }
        if (! in_array($type, $this->contentDrafts->types(), true)) {
            throw new AgentToolException('type must be one of: '.implode(', ', $this->contentDrafts->types()));
        }
        if ($language === '') {
            throw new AgentToolException('language is required.');
        }
        if ($sourceText === '') {
            throw new AgentToolException('source_text is required.');
        }
        if ($level !== null && ! in_array($level, $this->contentDrafts->levels(), true)) {
            $level = null;
        }

        $content = $this->contentDrafts->create([
            'type' => $type,
            'title' => $title,
            'language' => strtolower($language),
            'level' => $level,
            'source_text' => $sourceText,
            'created_by' => $context->actingUserId,
        ]);

        return [
            'content_id' => $content['id'],
            'title' => $content['title'],
            'status' => $content['status'],
            'admin_url' => route('filament.admin.resources.contents.edit', $content['id']),
        ];
    }
}
