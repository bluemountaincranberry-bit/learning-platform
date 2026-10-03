<?php

namespace App\Modules\Ai\Infrastructure;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Elasticsearch REST client over Laravel's `Http` facade.
 *
 * Not the official `elasticsearch/elasticsearch` package, deliberately:
 * that package is already installed at v9.3.0 (pulled in transitively by
 * `babenkoivan/elastic-scout-driver` ^5.0, a pre-existing composer.json
 * dependency unrelated to this epic), and v9 of that client hardcodes an
 * `Accept`/`Content-Type` API-compatibility header of
 * `compatible-with=9`(`Client::API_COMPATIBILITY_HEADER`, not
 * configurable). This environment runs Elasticsearch **8.15.0**
 * (docker-compose.yml), which rejects that header with a 400
 * `media_type_header_exception` — confirmed by hitting it directly, not
 * assumed. Downgrading `elasticsearch/elasticsearch` to a v8 release
 * conflicts with `babenkoivan/elastic-client`'s `^9.0` requirement
 * (composer reports an unresolvable conflict), and unwinding that
 * dependency is out of scope for EPIC 2.
 *
 * The RAG corpus needs exactly four operations (index exists/create,
 * index a document, kNN search) — a thin wrapper over `Http` covers that
 * with no version coupling, and matches the pattern already used for
 * `OpenAiClient`/`OpenAiEmbeddingsClient` (plain HTTP, no vendor SDK)
 * instead of adding a second, conflicting client dependency.
 */
class ElasticsearchClient
{
    public function __construct(
        private readonly string $baseUrl
    ) {}

    public function indexExists(string $index): bool
    {
        return $this->http()->head("/{$index}")->successful();
    }

    /**
     * @param  array<string, mixed>  $mappings
     */
    public function createIndex(string $index, array $mappings): void
    {
        $this->http()->put("/{$index}", ['mappings' => $mappings])->throw();
    }

    public function deleteIndex(string $index): void
    {
        $response = $this->http()->delete("/{$index}");
        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function indexDocument(string $index, string $id, array $document): void
    {
        // refresh=wait_for: the document is searchable by the time this
        // call returns — see RagIndexingService for why that trade-off
        // (a slightly slower write) is acceptable at this corpus size.
        $this->http()->put("/{$index}/_doc/{$id}?refresh=wait_for", $document)->throw();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed> Decoded response body (empty hits when the index doesn't exist).
     */
    public function search(string $index, array $body): array
    {
        $response = $this->http()->post("/{$index}/_search", $body);

        if ($response->status() === 404) {
            return ['hits' => ['hits' => []]];
        }

        return $response->throw()->json();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)->acceptJson()->asJson();
    }
}
