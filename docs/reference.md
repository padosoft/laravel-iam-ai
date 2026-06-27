---
title: Reference
description: Every class shipped by laravel-iam-ai, grouped by namespace, with signatures.
---

# Reference

All classes live under `Padosoft\Iam\Ai\`.

## `AdvisoryClient`

The governance orchestrator.

```php
public function __construct(
    AiProvider $provider,
    Redactor $redactor,
    HallucinationGuard $guard,
    ?AuditRecorder $audit = null,
);

/**
 * @param array<array-key, mixed> $evidence    real facts the model may cite
 * @param list<string>            $allowedRefs  identifiers allowed in the output
 */
public function advise(
    string $task,
    string $system,
    string $userPrompt,
    array $evidence,
    array $allowedRefs,
    string $deterministicFallback,
): Advisory;
```

Pipeline: reset `Redactor::didRedact` → redact prompt + evidence → if disabled/transport-throws/guard-fails
return `deterministicFallback` → otherwise redact the output (defense-in-depth) → audit → return `Advisory`.

## `Advisory` *(final readonly)*

```php
public function __construct(
    string $text,
    array $citations = [],      // list<string> — refs to real evidence cited
    bool $aiUsed = false,
    bool $redacted = false,
    bool $guardPassed = true,
    array $violations = [],     // list<string> — invented ids caught by the guard
    string $provider = 'deterministic',
);

public function toArray(): array; // adds 'advisory_only' => true
```

## `Modules\AccessExplainer`

```php
public function __construct(AdvisoryClient $client);

/**
 * @param array<string, mixed> $decision  PDP output (toArray): allowed, decision_id, explanation[], matched[]
 */
public function explain(array $decision, string $question = ''): Advisory;
```

Fail-closed: only a true boolean `allowed` reads as `CONSENTITO`; anything else is `NEGATO`. Cites only the
real `decision_id` and matched keys.

## `Governance\Redactor`

```php
public bool $didRedact = false;

/** @phpstan-impure mutates $didRedact */
public function redact(string $text): string;

/**
 * @param array<array-key, mixed> $data
 * @return array<array-key, mixed>
 * @phpstan-impure delegates to redact()
 */
public function redactArray(array $data): array;
```

Redacts Bearer/Basic, JWT, PEM private keys, explicit `password|secret|token|otp|cookie|session_id`,
emails, IPv4, long hex and base64 blobs. Order matters: hex before base64.

## `Governance\HallucinationGuard`

```php
/**
 * @param list<string> $allowedRefs
 * @return list<string> refs cited in the output but NOT allowed
 */
public function violations(string $output, array $allowedRefs): array;

/** @param list<string> $allowedRefs */
public function passes(string $output, array $allowedRefs): bool;
```

Recognises prefixed IDs (`xxx_…` / `xxx-…`), bare ULIDs (26 chars) and UUIDs.

## `Contracts\AiProvider`

```php
public function name(): string;                          // 'regolo' | 'ollama' | 'deterministic' | ...
public function complete(string $system, string $user): string;
```

## `Providers\DisabledProvider` *(implements `AiProvider`)*

The safe default. `name()` returns `'disabled'`; `complete()` throws — the `AdvisoryClient` then falls back
to deterministic text. No network calls.

## `IamAiServiceProvider`

Registers the governance services and resolves `AiProvider` from `config('iam-ai.provider')` (default
`DisabledProvider`). Sovereign adapters rebind `AiProvider` when installed.

## Config — `config/iam-ai.php`

See [Configuration](configuration.md) for every key. Defaults: `enabled=false`, `provider=disabled`,
`redaction=true`, `store_prompts=false`, `store_outputs=false`, `max_context_events=50`.
