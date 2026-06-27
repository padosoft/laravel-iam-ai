<?php

declare(strict_types=1);

namespace Padosoft\Iam\Ai\Providers;

use Padosoft\Iam\Ai\Contracts\AiProvider;

/**
 * Provider di default quando l'AI è spenta (doc 15 §1: enabled=false di default, sovrano). Non
 * effettua alcuna chiamata di rete: se invocato (misconfiguration) lancia, e l'AdvisoryClient ricade
 * sul testo deterministico. Garantisce che, out-of-the-box, nessun dato lasci il perimetro.
 */
final class DisabledProvider implements AiProvider
{
    public function name(): string
    {
        return 'disabled';
    }

    public function complete(string $system, string $user): string
    {
        throw new \RuntimeException('AI disabilitata: nessun provider di transport configurato (iam-ai.enabled=false).');
    }
}
