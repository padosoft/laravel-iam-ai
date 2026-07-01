<?php

declare(strict_types=1);

namespace Padosoft\Iam\Ai;

use Padosoft\Iam\Ai\Contracts\AiProvider;
use Padosoft\Iam\Ai\Providers\DisabledProvider;
use Padosoft\Iam\Ai\Providers\OllamaProvider;
use Padosoft\Iam\Ai\Providers\RegoloProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider del modulo opzionale `-ai` (doc 15). Registra la governance (Redactor,
 * HallucinationGuard, AdvisoryClient) e risolve il transport in base a `iam-ai.provider`. Default
 * = DisabledProvider (sovrano, nessuna chiamata di rete). Gli adapter reali (Regolo/Ollama) si
 * registrano sostituendo il binding di AiProvider dai loro pacchetti.
 */
final class IamAiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-iam-ai')->hasConfigFile('iam-ai');
    }

    public function packageRegistered(): void
    {
        $this->app->bind(AiProvider::class, function (): AiProvider {
            $provider = config('iam-ai.provider', 'disabled');

            // Sovereign, fail-safe resolution. A real adapter is used only when its transport is fully
            // configured; any missing setting falls back to DisabledProvider so a half-configured provider
            // never makes a network call. `disabled` (and any unknown value) stays sovereign by default.
            return match ($provider) {
                'regolo' => self::makeRegolo(),
                'ollama' => self::makeOllama(),
                default => new DisabledProvider,
            };
        });
    }

    private static function makeRegolo(): AiProvider
    {
        $baseUrl = config('iam-ai.base_url');
        $apiKey = config('iam-ai.api_key');

        if (!is_string($baseUrl) || $baseUrl === '' || !is_string($apiKey) || $apiKey === '') {
            return new DisabledProvider; // misconfigured → sovereign fallback, no network call.
        }

        return new RegoloProvider($baseUrl, $apiKey, self::configModel(), self::configTimeout(20));
    }

    private static function makeOllama(): AiProvider
    {
        $baseUrl = config('iam-ai.base_url');

        if (!is_string($baseUrl) || $baseUrl === '') {
            return new DisabledProvider; // no endpoint → sovereign fallback.
        }

        $apiKey = config('iam-ai.api_key');

        return new OllamaProvider(
            $baseUrl,
            self::configModel(),
            self::configTimeout(30),
            is_string($apiKey) && $apiKey !== '' ? $apiKey : null,
        );
    }

    private static function configModel(): ?string
    {
        $model = config('iam-ai.model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    private static function configTimeout(int $default): int
    {
        $timeout = config('iam-ai.timeout');

        return is_numeric($timeout) ? (int) $timeout : $default;
    }
}
