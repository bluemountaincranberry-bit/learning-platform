<?php

namespace App\Modules\Ai\Application\Agent;

use App\Contracts\Ai\LessonNotesWriterInterface;
use App\Exceptions\PdfExtractionException;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Domain\Models\AgentMessage;
use App\Modules\Content\Application\Contracts\PdfTextExtractorInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Folds the *whole* text of a PDF attached to a lesson chat into the
 * lesson's notes. `ExtractPdfTextTool` hands the model only a bounded
 * excerpt (the model context stays small), so the notes — which
 * "Разобрать урок" analyzes in parts — are filled from a fresh, uncapped
 * extraction of the same attachment instead of that excerpt (VIK-70).
 */
class LessonPdfNotesFolder
{
    public function __construct(
        private readonly PdfTextExtractorInterface $extractor,
        private readonly LessonNotesWriterInterface $notes,
    ) {}

    /**
     * @param  string  $fallbackText  the (possibly truncated) tool output, used only if the attachment cannot be re-read
     */
    public function fold(AgentConversation $conversation, ?int $attachmentMessageId, string $fallbackText): void
    {
        if ($conversation->lesson_id === null) {
            return;
        }

        $text = $this->fullText($conversation, $attachmentMessageId) ?? trim($fallbackText);

        if ($text !== '') {
            $this->notes->appendNotes($conversation->lesson_id, $text);
        }
    }

    private function fullText(AgentConversation $conversation, ?int $attachmentMessageId): ?string
    {
        if ($attachmentMessageId === null) {
            return null;
        }

        $message = AgentMessage::query()
            ->where('agent_conversation_id', $conversation->id)
            ->find($attachmentMessageId);

        if ($message === null || $message->attachment_path === null || ! Storage::disk('local')->exists($message->attachment_path)) {
            return null;
        }

        try {
            return trim($this->extractor->extractFromPath(Storage::disk('local')->path($message->attachment_path)));
        } catch (PdfExtractionException $e) {
            Log::warning('LessonPdfNotesFolder: could not re-read attachment', ['message_id' => $message->id, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
