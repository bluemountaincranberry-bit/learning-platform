<?php

namespace App\Modules\Srs\Application\Contracts;

interface ReviewScheduleReaderInterface
{
    /** @return array{due_now_count: int, upcoming: array<int, array{item: string, state: string, next_review_at: ?string, is_due: bool}>} */
    public function forUser(int $userId, int $limit): array;

    /** @return array<int, string> */
    public function wordsForQuiz(int $userId, int $limit): array;

    /** @return list<int> */
    public function overdueContentIds(int $userId): array;

    /** @return list<array{id:int, content_id:int, content_lexeme_id:?int, item_key:string, lexeme_display:string, state:string, next_review_at:?string}> */
    public function dueCards(int $userId, ?int $contentId = null): array;

    /** @param list<int> $lexemeIds @return list<array{id:int, lexeme_id:int, content_id:?int, content_lexeme_id:?int, item_key:?string, lexeme_display:string, state:string, next_review_at:?string}> */
    public function selectedCards(int $userId, array $lexemeIds): array;
}
