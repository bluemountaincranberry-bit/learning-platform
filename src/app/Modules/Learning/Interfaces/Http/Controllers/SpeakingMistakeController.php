<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Learning\Domain\Models\SpeakingMistake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpeakingMistakeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $status = $request->query('status', 'active');
        abort_unless(in_array($status, ['active', 'mastered', 'hidden', 'all'], true), 422);

        $query = SpeakingMistake::query()->where('user_id', $userId);
        if ($status !== 'all') $query->where('status', $status);
        $mistakes = $query->orderByDesc('last_seen_at')->orderByDesc('id')->limit(200)->get();

        $weeklyTrend = SpeakingMistake::query()->where('user_id', $userId)
            ->where('created_at', '>=', now()->subWeeks(7)->startOfWeek())
            ->selectRaw('DATE_TRUNC(\'week\', created_at) as week, count(*) as count')
            ->groupBy('week')->orderBy('week')->get()
            ->map(fn ($row) => ['week' => $row->week, 'count' => (int) $row->count]);

        return response()->json([
            'mistakes' => $mistakes->map(fn (SpeakingMistake $mistake) => $this->payload($mistake)),
            'summary' => [
                'active' => SpeakingMistake::query()->where('user_id', $userId)->where('status', 'active')->count(),
                'mastered' => SpeakingMistake::query()->where('user_id', $userId)->where('status', 'mastered')->count(),
                'hidden' => SpeakingMistake::query()->where('user_id', $userId)->where('status', 'hidden')->count(),
            ],
            'weekly_trend' => $weeklyTrend,
            'by_category' => SpeakingMistake::query()->where('user_id', $userId)->where('status', 'active')
                ->select('category', DB::raw('count(*) as count'))->groupBy('category')->orderByDesc('count')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'language' => ['required', 'string', 'max:16'],
            'native_language' => ['required', 'string', 'max:16'],
            'prompt_text' => ['nullable', 'string', 'max:4000'],
            'original_text' => ['required', 'string', 'max:4000'],
            'corrected_text' => ['required', 'string', 'max:4000'],
            'explanation' => ['nullable', 'string', 'max:4000'],
            'category' => ['required', 'in:grammar,vocabulary,articles,word_order,verb_tense,preposition,pronunciation,general'],
            'source_type' => ['sometimes', 'in:chat,speaking_practice'],
            'source_id' => ['sometimes', 'nullable', 'integer'],
            'confidence' => ['sometimes', 'in:clear,uncertain'],
            'confirmed' => ['sometimes', 'boolean'],
        ]);

        $confidence = $data['confidence'] ?? 'clear';
        if ($confidence === 'uncertain' && ! ($data['confirmed'] ?? false)) {
            return response()->json(['requires_confirmation' => true], 202);
        }

        $mistake = SpeakingMistake::query()->where('user_id', $request->user()->id)
            ->where('language', $data['language'])->where('original_text', $data['original_text'])
            ->where('corrected_text', $data['corrected_text'])->where('status', 'active')->first();

        if ($mistake) {
            $mistake->forceFill(['last_seen_at' => now(), 'consecutive_correct' => 0])->save();
        } else {
            $mistake = SpeakingMistake::query()->create([
                'user_id' => $request->user()->id,
                ...$data,
                'source_type' => $data['source_type'] ?? 'speaking_practice',
                'confidence' => $confidence,
                'status' => 'active',
                'last_seen_at' => now(),
            ]);
        }

        return response()->json(['mistake' => $this->payload($mistake), 'saved_automatically' => $confidence === 'clear'], 201);
    }

    public function update(Request $request, SpeakingMistake $mistake): JsonResponse
    {
        abort_unless($mistake->user_id === $request->user()->id, 404);
        $data = $request->validate(['status' => ['required', 'in:active,mastered,hidden']]);
        $mistake->forceFill([
            'status' => $data['status'],
            'archived_at' => $data['status'] === 'hidden' ? now() : null,
            'consecutive_correct' => $data['status'] === 'active' ? 0 : $mistake->consecutive_correct,
        ])->save();

        return response()->json(['mistake' => $this->payload($mistake)]);
    }

    public function outcome(Request $request, SpeakingMistake $mistake): JsonResponse
    {
        abort_unless($mistake->user_id === $request->user()->id, 404);
        abort_unless($mistake->status === 'active', 422);
        $data = $request->validate(['correct' => ['required', 'boolean']]);
        $streak = $data['correct'] ? $mistake->consecutive_correct + 1 : 0;
        $status = $streak >= 3 ? 'mastered' : 'active';
        $mistake->forceFill([
            'consecutive_correct' => $streak,
            'status' => $status,
            'last_seen_at' => now(),
        ])->save();

        return response()->json(['mistake' => $this->payload($mistake)]);
    }

    /** @return array<string, mixed> */
    private function payload(SpeakingMistake $mistake): array
    {
        return [
            'id' => $mistake->id,
            'language' => $mistake->language,
            'original_text' => $mistake->original_text,
            'corrected_text' => $mistake->corrected_text,
            'prompt_text' => $mistake->prompt_text,
            'explanation' => $mistake->explanation,
            'category' => $mistake->category,
            'source_type' => $mistake->source_type,
            'source_id' => $mistake->source_id,
            'status' => $mistake->status,
            'confidence' => $mistake->confidence,
            'consecutive_correct' => $mistake->consecutive_correct,
            'last_seen_at' => $mistake->last_seen_at,
            'created_at' => $mistake->created_at,
        ];
    }
}
