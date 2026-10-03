<?php

namespace App\Contracts\Ai;

interface LessonNotesWriterInterface
{
    public function appendNotes(int $lessonId, string $text): void;
}
