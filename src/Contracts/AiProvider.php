<?php

declare(strict_types=1);

namespace Padosoft\Iam\Ai\Contracts;

/**
 * Transport AI astratto (doc 15 §2). Il modulo `-ai` NON dipende da un provider concreto: gli
 * adapter reali (Regolo/Ollama via laravel/ai) implementano questa interfaccia in pacchetti
 * opzionali. Il default è sovrano (UE/on-prem), MAI OpenAI. La governance (redaction, hallucination
 * guard, audit) vive sopra questo contratto, indipendente dal transport.
 */
interface AiProvider
{
    /** Identificativo del provider (per audit/telemetria), es. "regolo", "ollama", "deterministic". */
    public function name(): string;

    /** Completa un prompt (system + user) e ritorna il testo grezzo del modello. */
    public function complete(string $system, string $user): string;
}
