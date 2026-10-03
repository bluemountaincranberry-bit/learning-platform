<?php

namespace App\Modules\Learning\Application\Contracts;

interface LessonNotesWriterInterface
{
    public function appendNotes(int $lessonId, string $text): void;
}
