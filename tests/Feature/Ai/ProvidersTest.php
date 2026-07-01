<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Padosoft\Iam\Ai\Contracts\AiProvider;
use Padosoft\Iam\Ai\Providers\DisabledProvider;
use Padosoft\Iam\Ai\Providers\OllamaProvider;
use Padosoft\Iam\Ai\Providers\RegoloProvider;

/*
 * Real sovereign transports (Regolo / Ollama) and the fail-safe binding resolution.
 * Every network call is faked — no real endpoint is hit.
 */

it('RegoloProvider posts an OpenAI-style chat completion and returns the content', function () {
    Http::fake([
        'api.regolo.ai/*' => Http::response(['choices' => [['message' => ['content' => 'least-privilege advice']]]], 200),
    ]);

    $provider = new RegoloProvider('https://api.regolo.ai/v1', 'sk-secret', 'maestrale', 20);

    expect($provider->name())->toBe('regolo')
        ->and($provider->complete('sys', 'user'))->toBe('least-privilege advice');

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/chat/completions')
            && $request->hasHeader('Authorization', 'Bearer sk-secret')
            && $request['model'] === 'maestrale'
            && $request['messages'][0]['role'] === 'system'
            && $request['messages'][1]['content'] === 'user'
            && $request['temperature'] === 0;
    });
});

it('RegoloProvider throws on a non-2xx response (→ deterministic fallback upstream)', function () {
    Http::fake(['api.regolo.ai/*' => Http::response('nope', 503)]);

    (new RegoloProvider('https://api.regolo.ai/v1', 'sk', 'm'))->complete('s', 'u');
})->throws(RuntimeException::class);

it('RegoloProvider throws on a malformed body', function () {
    Http::fake(['api.regolo.ai/*' => Http::response(['choices' => []], 200)]);

    (new RegoloProvider('https://api.regolo.ai/v1', 'sk', 'm'))->complete('s', 'u');
})->throws(RuntimeException::class);

it('OllamaProvider posts /api/chat non-streaming and returns message.content', function () {
    Http::fake([
        'localhost:11434/*' => Http::response(['message' => ['role' => 'assistant', 'content' => 'on-prem advice']], 200),
    ]);

    $provider = new OllamaProvider('http://localhost:11434', 'llama3', 30);

    expect($provider->name())->toBe('ollama')
        ->and($provider->complete('sys', 'user'))->toBe('on-prem advice');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/chat') && $request['stream'] === false);
});

it('resolves provider=regolo to RegoloProvider only when base_url AND api_key are set', function () {
    config(['iam-ai.provider' => 'regolo', 'iam-ai.base_url' => 'https://api.regolo.ai/v1', 'iam-ai.api_key' => 'sk', 'iam-ai.model' => 'm']);
    expect(app(AiProvider::class))->toBeInstanceOf(RegoloProvider::class);

    // Missing api_key → sovereign fallback, never a misconfigured network call.
    config(['iam-ai.api_key' => null]);
    app()->forgetInstance(AiProvider::class);
    expect(app(AiProvider::class))->toBeInstanceOf(DisabledProvider::class);
});

it('resolves provider=ollama to OllamaProvider when base_url is set, else DisabledProvider', function () {
    config(['iam-ai.provider' => 'ollama', 'iam-ai.base_url' => 'http://localhost:11434']);
    expect(app(AiProvider::class))->toBeInstanceOf(OllamaProvider::class);

    config(['iam-ai.base_url' => null]);
    app()->forgetInstance(AiProvider::class);
    expect(app(AiProvider::class))->toBeInstanceOf(DisabledProvider::class);
});

it('provider=disabled (default) stays sovereign', function () {
    config(['iam-ai.provider' => 'disabled']);
    expect(app(AiProvider::class))->toBeInstanceOf(DisabledProvider::class);
});
