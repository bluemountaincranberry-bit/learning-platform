<?php

namespace App\Contracts\Ai;

interface LessonAssistant
{
    public function createConversation(int $lessonId, int $userId): int;

    public function conversationId(int $lessonId): ?int;

    /** @return array{messages:array<int, array<string, mixed>>, is_waiting:bool} */
    public function messages(int $lessonId): array;

    public function sendMessage(int $lessonId, string $content, ?string $attachmentPath, ?string $attachmentName): void;

    public function dispatchAnalysis(int $runId): void;
}
