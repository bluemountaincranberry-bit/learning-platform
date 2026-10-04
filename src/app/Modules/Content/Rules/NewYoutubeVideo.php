<?php

namespace App\Modules\Content\Rules;

use App\Modules\Content\Domain\ContentSourceKey;
use App\Modules\Content\Domain\Models\Content;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * VIK-16: a submitted YouTube URL must name one video that is not in the
 * catalog yet, whatever URL form it was pasted in and whoever added it.
 * The old check compared the raw URL string per submitter, so
 * `youtu.be/ID?si=…` and `watch?v=ID&list=…` created two contents.
 */
final class NewYoutubeVideo implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = ContentSourceKey::for('youtube', is_string($value) ? $value : null);

        if ($key === null) {
            $fail('The :attribute must link to a single YouTube video.');

            return;
        }

        $existing = Content::query()->where('source_key', $key)->orderBy('id')->first(['id', 'title', 'status', 'created_by']);

        if ($existing === null) {
            return;
        }

        // Name the existing content only when the submitter may see it; a
        // private submission of someone else's stays anonymous.
        $visible = $existing->isPubliclyVisible()
            || ($existing->created_by !== null && $existing->created_by === auth()->id());

        $fail($visible
            ? sprintf('This video is already in the catalog: "%s".', $existing->title)
            : 'This video is already in the catalog.');
    }
}
