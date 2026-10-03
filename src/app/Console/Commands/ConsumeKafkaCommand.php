<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Infrastructure\Domain\Models\EventLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConsumeKafkaCommand extends Command
{
    protected $signature = 'kafka:consume
                            {topic : Topic to consume (e.g. content.lifecycle)}
                            {--group= : Consumer group (default from config)}';

    protected $description = 'Consume messages from a Kafka topic; log and write to event_log. Run when KAFKA_ENABLED=true and rdkafka extension is loaded.';

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

        $topic = $this->argument('topic');
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
                    $payload = $message->payload;
                    $key = $message->key ? (is_string($message->key) ? $message->key : (string) $message->key) : null;
                    Log::info('Kafka message consumed', [
                        'topic' => $message->topic_name,
                        'partition' => $message->partition,
                        'offset' => $message->offset,
                        'key' => $key,
                    ]);
                    EventLog::query()->create([
                        'topic' => $message->topic_name,
                        'partition' => $message->partition,
                        'offset' => $message->offset,
                        'key' => $key,
                        'payload' => $payload,
                    ]);
                    $consumer->commit($message);
                    $this->line(sprintf('[%s] %s/%d offset %d', $message->topic_name, $message->partition, $message->offset, $message->offset));
                    break;
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    continue 2;
                default:
                    Log::warning('Kafka consume error', ['err' => $message->err, 'errstr' => $message->errstr()]);
                    $this->warn('Kafka error: ' . $message->errstr());
            }
        }
    }
}
