<?php

declare(strict_types=1);

/*
 * Configurazione del modulo AI (doc 15 §2). Default SOVRANO e SPENTO: `enabled=false`, nessun dato
 * lascia il perimetro finché non lo si accende esplicitamente scegliendo un provider sovrano
 * (Regolo UE / Ollama on-prem). MAI OpenAI di default. La redaction è sempre attiva; i prompt non
 * si memorizzano, e nemmeno gli output di default (privacy-by-default): vanno abilitati
 * esplicitamente, e in tal caso sono comunque sanificati prima della persistenza.
 */
return [
    'enabled' => env('IAM_AI_ENABLED', false),

    // 'disabled' (default) | 'regolo' | 'ollama' — i provider reali sono adapter opzionali.
    'provider' => env('IAM_AI_PROVIDER', 'disabled'),
    'model' => env('IAM_AI_MODEL'),

    'redaction' => true,            // pipeline di redaction pre-prompt (obbligatoria)
    'store_prompts' => false,       // mai persistere i prompt (possibile PII/segreti)
    'store_outputs' => env('IAM_AI_STORE_OUTPUTS', false), // opt-in: output (sanificati) in audit
    'max_context_events' => 50,     // tetto agli eventi passati come contesto
];
