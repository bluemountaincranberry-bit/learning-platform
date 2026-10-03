<?php

namespace App\Modules\Ai\Application\Agent\Tools;

use App\Exceptions\AgentToolException;
use App\Exceptions\PdfExtractionException;
use App\Modules\Ai\Domain\Models\AgentMessage;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Content\Application\Contracts\PdfTextExtractorInterface;
use Illuminate\Support\Facades\Storage;

/**
 * Reads the text layer of a PDF the admin attached earlier in this
 * conversation. Takes a message id (visible to the model in the chat
 * history) rather than a filesystem path — the model never sees real paths.
 *
 * `text` is untrusted, externally-sourced content (the PDF could contain
 * embedded instructions like "ignore previous instructions and..."), so it
 * is wrapped in explicit `<tool_output>` boundaries — the agent's system
 * prompt (see `ContentAgentService::systemPromptText()`) tells the model
 * that anything between those tags is data to analyze, never instructions
 * to follow. See docs/architecture/agent-framework-roadmap.md, step 5.9.
 */
class ExtractPdfTextTool implements AgentTool
{
    public function __construct(
        private readonly PdfTextExtractorInterface $extractor
    ) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'extract_pdf_text',
            description: 'Extracts the text content of a PDF the admin attached in this conversation. Call this before trying to read/summarize an attached PDF.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'attachment_message_id' => [
                        'type' => 'integer',
                        'description' => 'The message id that carries the attachment, as shown in the conversation.',
                    ],
                ],
                'required' => ['attachment_message_id'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $messageId = $arguments['attachment_message_id'] ?? null;
        if (! is_int($messageId) && ! ctype_digit((string) $messageId)) {
            throw new AgentToolException('attachment_message_id must be an integer.');
        }

        $message = AgentMessage::query()
            ->where('agent_conversation_id', $context->conversationId)
            ->find((int) $messageId);

        if (! $message || $message->attachment_path === null) {
            throw new AgentToolException('No attachment found on that message in this conversation.');
        }

        if (! Storage::disk('local')->exists($message->attachment_path)) {
            throw new AgentToolException('The attached file is no longer available in storage.');
        }

        try {
            $text = $this->extractor->extractFromPath(Storage::disk('local')->path($message->attachment_path));
        } catch (PdfExtractionException $e) {
            throw new AgentToolException($e->getMessage());
        }

        $maxChars = (int) config('ai.analysis.max_transcript_chars', 8000);
        $truncated = mb_strlen($text) > $maxChars;
        $text = $truncated ? mb_substr($text, 0, $maxChars) : $text;

        return [
            'text' => "<tool_output>\n{$text}\n</tool_output>",
            'truncated' => $truncated,
            'char_count' => mb_strlen($text),
        ];
    }
}
