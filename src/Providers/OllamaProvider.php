<?php

declare(strict_types=1);

namespace Padosoft\Iam\Ai\Providers;

use Illuminate\Support\Facades\Http;
use Padosoft\Iam\Ai\Contracts\AiProvider;

/**
 * On-prem / self-hosted transport adapter for Ollama (`POST /api/chat`, non-streaming). Fully sovereign:
 * the model runs on your own infrastructure, no data leaves the perimeter. An optional bearer token
 * supports Ollama behind an authenticating gateway. Same fail-closed contract as the other adapters: any
 * transport failure throws and the AdvisoryClient falls back to the deterministic text.
 */
final class OllamaProvider implements AiProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $model,
        private readonly int $timeout = 30,
        private readonly ?string $apiKey = null,
    ) {}

    public function name(): string
    {
        return 'ollama';
    }

    public function complete(string $system, string $user): string
    {
        $request = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson();

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $request = $request->withToken($this->apiKey);
        }

        $response = $request->post('/api/chat', [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'stream' => false,
            'options' => ['temperature' => 0],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("ollama transport: HTTP {$response->status()}");
        }

        $text = $response->json('message.content');
        if (!is_string($text) || $text === '') {
            throw new \RuntimeException('ollama transport: empty or malformed completion');
        }

        return $text;
    }
}
