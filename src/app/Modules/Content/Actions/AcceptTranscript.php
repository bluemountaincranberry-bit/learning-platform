<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Events\TranscriptAccepted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptTranscript
{
    public function execute(Content $content, ?int $actorId): Content
    {
        if (! $content->hasTranscript()) {
            throw ValidationException::withMessages([
                'transcript' => 'Transcript is empty.',
            ]);
        }

        return DB::transaction(function () use ($content, $actorId): Content {
            $content->update([
                'transcript_accepted_at' => now(),
                'transcript_accepted_by' => $actorId,
            ]);

            $accepted = $content->refresh();
            \App\Modules\Infrastructure\Domain\Models\OutboxEvent::record(TranscriptAccepted::class, 'content', $accepted->id, ['content_id' => $accepted->id, 'actor_id' => $actorId]);
            DB::afterCommit(fn () => TranscriptAccepted::dispatch($accepted->id, $actorId));
            return $accepted;
        });
    }
}
