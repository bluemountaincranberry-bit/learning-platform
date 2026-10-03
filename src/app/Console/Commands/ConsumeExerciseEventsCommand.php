<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Infrastructure\Domain\Models\EventLog;
use App\Modules\Learning\Interfaces\Listeners\PublishExerciseCompletedToKafka;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Task 4.13 — dedicated consumer for the `exercise.lifecycle` topic
 * (`ExerciseCompleted`/`PublishExerciseCompletedToKafka`), extending the
 * same rdkafka consume loop `ConsumeKafkaCommand` already has (broker/group
 * config, `EventLog` durability, offset commits) rather than inventing a
 * second consumption mechanism — the difference is this command is bound
 * to one specific topic and gives the decoded message somewhere real to go
 * (`handleMessage()`) instead of only logging generically.
 *
 * **Scope, stated plainly**: this reliably captures the event stream
 * (`event_log`, same durability every topic already gets) and logs a
 * structured "mistake observed" line — it does **not** write to
 * Elasticsearch. Turning these events into queryable semantic-memory
 * observations is EPIC 2.6 (`SemanticMemoryService`, a new ES index),
 * which this task unblocks but does not implement — that index doesn't
 * exist yet. `handleMessage()` is the seam a future 2.6 implementation
 * extends.
 *
 * Same environment constraint as `ConsumeKafkaCommand`: requires the
 * rdkafka PHP extension and `KAFKA_ENABLED=true`, neither of which is
 * available to actually run this end-to-end against a live broker in this
 * environment — `handleMessage()` is deliberately factored out of the
 * rdkafka loop so its logic can be unit-tested without either.
 */
class ConsumeExerciseEventsCommand extends Command
{
    protected $signature = 'kafka:consume-exercise-events
                            {--group= : Consumer group (default from config)}';

    protected $description = 'Consume exercise.lifecycle (ExerciseCompleted) events; log and write to event_log. Run when KAFKA_ENABLED=true and rdkafka is loaded.';

    public function handle(): int
    {
        if (! extension_loaded('rdkafka')) {
            $this->error('rdkafka PHP extension is not loaded. Rebuild the app image with rdkafka enabled.');

            return self::FAILURE;
        }

        if (! config('kafka.enabled')) {
            $this->warn('Kafka is disabled (KAFKA_ENABLED=false). Enable it in .env to run the consumer.');

            return self::FAILURE;
        }

        $topic = PublishExerciseCompletedToKafka::TOPIC;
        $group = $this->option('group') ?: config('kafka.consumer_group', 'laravel-learning-consumer');
        $brokers = config('kafka.brokers', 'localhost:9092');

        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', $brokers);
        $conf->set('group.id', $group);
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new \RdKafka\KafkaConsumer($conf);
        $consumer->subscribe([$topic]);

        $this->info("Consuming topic: {$topic} (group: {$group}). Ctrl+C to stop.");

        while (true) {
            $message = $consumer->consume(60_000); // 60s timeout

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $key = $message->key ? (is_string($message->key) ? $message->key : (string) $message->key) : null;
                    $this->handleMessage($message->topic_name, $message->partition, $message->offset, $key, $message->payload);
                    $consumer->commit($message);
                    $this->line(sprintf('[%s] %s/%d offset %d', $message->topic_name, $message->partition, $message->offset, $message->offset));
                    break;
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    continue 2;
                default:
                    Log::warning('Kafka exercise-events consume error', ['err' => $message->err, 'errstr' => $message->errstr()]);
                    $this->warn('Kafka error: '.$message->errstr());
            }
        }
    }

    /**
     * The actual per-message logic, deliberately separate from the rdkafka
     * loop above so it's testable without a live broker (this class's own
     * docblock explains why that separation matters here specifically).
     */
    public function handleMessage(string $topicName, int $partition, int $offset, ?string $key, string $payload): void
    {
        Log::info('Kafka exercise event consumed', [
            'topic' => $topicName,
            'partition' => $partition,
            'offset' => $offset,
            'key' => $key,
        ]);

        EventLog::query()->create([
            'topic' => $topicName,
            'partition' => $partition,
            'offset' => $offset,
            'key' => $key,
            'payload' => $payload,
        ]);

        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || ($decoded['event_type'] ?? null) !== PublishExerciseCompletedToKafka::EVENT_TYPE) {
            return;
        }

        $eventPayload = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];

        if (($eventPayload['is_mistake'] ?? false) === true) {
            // Stub for EPIC 2.6's semantic-memory consolidation: real
            // implementation writes/updates an observation in the
            // semantic-memory Elasticsearch index instead of just logging.
            Log::info('Exercise mistake observed (semantic memory consolidation pending EPIC 2.6)', [
                'user_id' => $eventPayload['user_id'] ?? null,
                'item' => $eventPayload['item'] ?? null,
                'language' => $eventPayload['language'] ?? null,
            ]);
        }
    }
}
