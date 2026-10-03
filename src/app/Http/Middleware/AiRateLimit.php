<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AiRateLimit
{
    /**
     * @param  string  $feature  'explain' | 'chat' | 'sentence_practice' | ... — matches an
     *                           `ai.rate_limits.{feature}_per_day` config key.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $limit = (int) config("ai.rate_limits.{$feature}_per_day", 0);
        if ($limit <= 0) {
            return $next($request);
        }

        $date = now()->format('Y-m-d');
        $key = "ai:rate_limit:{$feature}:{$user->id}:{$date}";
        $count = (int) Cache::get($key, 0);
        if ($count >= $limit) {
            Log::warning('AI rate limit exceeded', ['user_id' => $user->id, 'feature' => $feature, 'limit' => $limit]);

            return response()->json([
                'message' => 'Daily limit reached for this feature. Try again tomorrow.',
            ], 429);
        }

        Cache::put($key, $count + 1, now()->endOfDay()->addSecond());

        return $next($request);
    }
}
