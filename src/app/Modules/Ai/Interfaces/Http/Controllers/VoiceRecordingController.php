<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Domain\Models\AgentMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VoiceRecordingController extends Controller
{
    public function stream(Request $request, AgentMessage $message): BinaryFileResponse
    {
        abort_unless($this->ownedMessage($message, (int) $request->user()->id), 404);
        $disk = Storage::disk($message->voice_audio_disk ?: 'local');
        abort_unless($message->voice_audio_path && $disk->exists($message->voice_audio_path), 404);

        return response()->file($disk->path($message->voice_audio_path), [
            'Content-Type' => $disk->mimeType($message->voice_audio_path) ?: 'audio/webm',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pin(Request $request, AgentMessage $message): JsonResponse
    {
        abort_unless($this->ownedMessage($message, (int) $request->user()->id), 404);
        abort_unless($message->voice_audio_path !== null, 404);

        $data = $request->validate(['pinned' => ['required', 'boolean']]);
        $pinned = (bool) $data['pinned'];
        $message->forceFill([
            'voice_audio_pinned' => $pinned,
            'voice_audio_expires_at' => $pinned ? null : now()->addDays(30),
        ])->save();

        return response()->json(['pinned' => $pinned, 'expires_at' => $message->voice_audio_expires_at]);
    }

    private function ownedMessage(AgentMessage $message, int $userId): bool
    {
        return $message->role === AgentMessage::ROLE_USER
            && $message->conversation()->where('created_by', $userId)->exists();
    }
}
