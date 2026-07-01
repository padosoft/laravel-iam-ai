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

    // 'disabled' (default) | 'regolo' (OpenAI-compatible, UE) | 'ollama' (on-prem).
    'provider' => env('IAM_AI_PROVIDER', 'disabled'),
    'model' => env('IAM_AI_MODEL'),

    // Transport del provider reale (Regolo/Ollama). Se `base_url` (e, per regolo, `api_key`) mancano,
    // il binding ricade in modo fail-safe su DisabledProvider: mai una chiamata di rete mal configurata.
    'base_url' => env('IAM_AI_BASE_URL'),   // es. https://api.regolo.ai/v1  oppure  http://localhost:11434
    'api_key' => env('IAM_AI_API_KEY'),     // richiesta da regolo; opzionale per ollama (gateway)
    'timeout' => (int) env('IAM_AI_TIMEOUT', 20), // secondi

    'redaction' => true,            // pipeline di redaction pre-prompt (obbligatoria)
    'store_prompts' => false,       // mai persistere i prompt (possibile PII/segreti)
    'store_outputs' => env('IAM_AI_STORE_OUTPUTS', false), // opt-in: output (sanificati) in audit
    'max_context_events' => 50,     // tetto agli eventi passati come contesto
];
