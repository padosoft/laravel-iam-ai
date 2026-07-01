<?php

declare(strict_types=1);

namespace Padosoft\Iam\Ai\Providers;

use Illuminate\Support\Facades\Http;
use Padosoft\Iam\Ai\Contracts\AiProvider;

/**
 * Sovereign (EU) transport adapter for Regolo.ai — an OpenAI-compatible chat-completions API.
 *
 * This is the real network transport behind the advisory pipeline: the AdvisoryClient has already
 * redacted the prompt before it reaches here, and it re-checks (hallucination guard) and re-redacts the
 * output afterwards. Any failure (network, timeout, non-2xx, malformed body) throws — the AdvisoryClient
 * catches it and falls back to the deterministic text, so a broken provider never breaks a decision and
 * never leaks an opaque error. Temperature is pinned to 0 for reproducible, conservative advice.
 */
final class RegoloProvider implements AiProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly ?string $model,
        private readonly int $timeout = 20,
    ) {}

    public function name(): string
    {
        return 'regolo';
    }

    public function complete(string $system, string $user): string
    {
        $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeout)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->post('/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0,
                'stream' => false,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("regolo transport: HTTP {$response->status()}");
        }

        $text = $response->json('choices.0.message.content');
        if (!is_string($text) || $text === '') {
            throw new \RuntimeException('regolo transport: empty or malformed completion');
        }

        return $text;
    }
}
