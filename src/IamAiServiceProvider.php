<?php

declare(strict_types=1);

namespace Padosoft\Iam\Ai;

use Padosoft\Iam\Ai\Contracts\AiProvider;
use Padosoft\Iam\Ai\Providers\DisabledProvider;
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

            // Default sovrano: nessun transport reale cablato nel core → DisabledProvider. Gli adapter
            // (regolo/ollama) ridefiniscono questo binding quando installati e configurati.
            return match ($provider) {
                default => new DisabledProvider,
            };
        });
    }
}
