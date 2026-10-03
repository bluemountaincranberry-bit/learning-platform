<?php

namespace App\Modules\Ai\Application\Contracts;

interface LessonNotesWriterInterface
{
    public function appendNotes(int $lessonId, string $text): void;
}
