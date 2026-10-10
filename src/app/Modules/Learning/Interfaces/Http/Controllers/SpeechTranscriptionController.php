<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Exceptions\AiClientException;
use App\Http\Controllers\Controller;
use App\Modules\Learning\Application\SpeechToTextProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SpeechTranscriptionController extends Controller
{
    public function __construct(private readonly SpeechToTextProviderFactory $providers) {}

    public function providers(): JsonResponse
    {
        return response()->json(['providers' => $this->providers->available()]);
    }

    public function transcribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'audio' => ['required', 'file', 'mimes:webm,mp4,m4a,ogg,wav,mpeg,mpga', 'max:10240'],
            'provider' => ['required', 'in:openai,local_whisper'],
            'language' => ['required', 'in:en,ru'],
        ]);

        if (! collect($this->providers->available())->contains('id', $data['provider'])) {
            return response()->json(['message' => 'This transcription provider is not configured.'], 422);
        }

        try {
            $result = $this->providers->make($data['provider'])->transcribe(
                $data['audio']->getRealPath(),
                $data['language'],
                $data['audio']->getClientOriginalName(),
            );
        } catch (AiClientException $exception) {
            Log::warning('Speech transcription provider failed.', [
                'provider' => $data['provider'],
                'language' => $data['language'],
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Could not transcribe this recording. Try the other provider or record again.'], 502);
        } catch (Throwable $exception) {
            Log::warning('Speech transcription failed.', ['provider' => $data['provider']]);

            return response()->json(['message' => 'Could not transcribe this recording. Try again.'], 502);
        }

        return response()->json(['transcription' => $result]);
    }
}
