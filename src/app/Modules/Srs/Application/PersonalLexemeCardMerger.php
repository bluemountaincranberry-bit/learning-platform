<?php

namespace App\Modules\Srs\Application;

use App\Modules\Srs\Application\Contracts\PersonalLexemeCardMergerInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PersonalLexemeCardMerger implements PersonalLexemeCardMergerInterface
{
    public function merge(int $userId, int $fromLexemeId, int $toLexemeId): void
    {
        $cards = DB::table('srs_cards')->where('user_id', $userId)
            ->whereIn('lexeme_id', [$fromLexemeId, $toLexemeId])->orderBy('id')->lockForUpdate()->get();
        if ($cards->isEmpty()) {
            return;
        }

        $survivor = $cards->first();
        $latest = $cards->sort(function (object $left, object $right): int {
            return [$right->updated_at ?? '', (int) $right->id] <=> [$left->updated_at ?? '', (int) $left->id];
        })->first();
        $cardIds = $cards->pluck('id')->all();
        DB::table('srs_reviews')->whereIn('srs_card_id', $cardIds)->update(['srs_card_id' => $survivor->id]);
        $due = $cards->pluck('next_review_at')->filter()->sort()->first() ?? Carbon::now()->toDateTimeString();
        $duplicates = $cards->pluck('id')->reject(fn ($id): bool => (int) $id === (int) $survivor->id)->all();
        if ($duplicates !== []) {
            DB::table('srs_cards')->whereIn('id', $duplicates)->delete();
        }
        DB::table('srs_cards')->where('id', $survivor->id)->update([
            'lexeme_id' => $toLexemeId,
            'state' => $cards->contains(fn (object $card): bool => $card->state === 'relearning') ? 'relearning' : $latest->state,
            'interval_days' => $latest->interval_days,
            'ease_factor' => $latest->ease_factor,
            'next_review_at' => $due,
            'deactivated_at' => $cards->every(fn (object $card): bool => $card->deactivated_at !== null)
                ? $cards->sortByDesc('deactivated_at')->first()->deactivated_at : null,
            'updated_at' => Carbon::now(),
        ]);
    }
}
