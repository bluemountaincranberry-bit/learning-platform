<?php

namespace App\Modules\Interview\Application;

use App\Modules\Interview\Domain\Models\InterviewAnswerVariant;
use App\Modules\Interview\Domain\Models\InterviewQuestion;
use App\Modules\Interview\Domain\Models\InterviewTag;
use App\Modules\Interview\Domain\Models\InterviewTopic;
use Illuminate\Support\Facades\DB;

final class InterviewStarterSeeder
{
    private const BANK = [
        'Behavioral interviews' => [
            ['Tell me about yourself.', 'Расскажите о себе.', ['introductory', 'behavioral']],
            ['Tell me about a challenge you overcame.', 'Расскажите о трудности, которую вы преодолели.', ['STAR', 'behavioral']],
            ['Tell me about a time you worked with someone as a team.', 'Расскажите о случае командной работы.', ['teamwork', 'behavioral']],
        ],
        'AI fundamentals' => [
            ['What is generative AI?', 'Что такое генеративный ИИ?', ['AI', 'fundamentals']],
            ['How can you reduce hallucinations in an AI application?', 'Как уменьшить количество галлюцинаций в приложении с ИИ?', ['AI', 'reliability']],
            ['When should a task use AI, and when should it use deterministic code?', 'Когда задаче нужен ИИ, а когда обычный детерминированный код?', ['AI', 'architecture']],
        ],
        'Copilot Studio and Power Platform' => [
            ['What is Microsoft Copilot Studio used for?', 'Для чего используется Microsoft Copilot Studio?', ['Copilot Studio', 'Power Platform']],
            ['How would you connect a copilot to an external system?', 'Как подключить copilot к внешней системе?', ['Copilot Studio', 'integration']],
            ['How do you test and publish a copilot safely?', 'Как безопасно тестировать и публиковать copilot?', ['Copilot Studio', 'testing']],
        ],
        'HTTP, API and REST' => [
            ['What is an API?', 'Что такое API?', ['API', 'fundamentals']],
            ['What is the difference between GET and POST?', 'В чём разница между GET и POST?', ['HTTP', 'REST']],
            ['What does an HTTP status code tell a client?', 'Что сообщает клиенту код состояния HTTP?', ['HTTP', 'fundamentals']],
        ],
        'Developer fundamentals' => [
            ['What is the purpose of version control?', 'Для чего нужна система контроля версий?', ['development', 'fundamentals']],
            ['How do you investigate a bug you cannot reproduce reliably?', 'Как вы исследуете ошибку, которую сложно стабильно воспроизвести?', ['debugging', 'problem-solving']],
            ['Why are automated tests useful?', 'Чем полезны автоматизированные тесты?', ['testing', 'fundamentals']],
        ],
    ];

    public function seedForUser(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            foreach (self::BANK as $topicName => $questions) {
                $topic = InterviewTopic::query()->firstOrCreate(['user_id' => $userId, 'parent_id' => null, 'name' => $topicName]);
                foreach ($questions as [$promptEn, $promptRu, $tagNames]) {
                    $question = InterviewQuestion::query()->firstOrCreate(
                        ['user_id' => $userId, 'prompt_en' => $promptEn],
                        ['topic_id' => $topic->id, 'prompt_ru' => $promptRu, 'preparation_state' => 'unpracticed']
                    );
                    foreach ($tagNames as $name) {
                        $tag = InterviewTag::query()->firstOrCreate(['user_id' => $userId, 'name' => $name]);
                        $question->tags()->syncWithoutDetaching([$tag->id]);
                    }
                    InterviewAnswerVariant::query()->firstOrCreate(
                        ['question_id' => $question->id, 'kind' => 'short'],
                        ['text_en' => null, 'text_ru' => null]
                    );
                    InterviewAnswerVariant::query()->firstOrCreate(
                        ['question_id' => $question->id, 'kind' => 'full'],
                        ['text_en' => null, 'text_ru' => null]
                    );
                }
            }
        });
    }
}
