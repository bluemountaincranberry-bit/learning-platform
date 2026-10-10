<?php

namespace App\Modules\Interview\Application;

final class InterviewQuestionDraftRules
{
    /** @return array<string, array<int, string>> */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'prompt_en' => ['required', 'string', 'max:2000'],
            $prefix.'prompt_ru' => ['nullable', 'string', 'max:2000'],
            $prefix.'topic_id' => ['nullable', 'integer'],
            $prefix.'tags' => ['sometimes', 'array'],
            $prefix.'tags.*' => ['string', 'max:80'],
        ];
    }
}
