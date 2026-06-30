---
title: The advisory pipeline
description: A stage-by-stage trace of AdvisoryClient::advise — redaction (pre), the enabled gate, transport, the hallucination guard, output redaction (defense-in-depth), and audit — with the exact Advisory each branch returns.
---

# The advisory pipeline

`AdvisoryClient::advise()` is the single governance pipeline every AI interaction passes through. This page
traces every stage so you know exactly where each behavior lives and which `Advisory` each branch returns.

## Signature

```php
public function advise(
    string $task,                  // audit label, e.g. 'access_explain'
    string $system,                // system prompt
    string $userPrompt,            // user question (redacted before use)
    array $evidence,               // real facts the model may cite (redacted)
    array $allowedRefs,            // identifiers the output may cite (guard whitelist)
    string $deterministicFallback, // the non-AI answer, always available
): Advisory;
```

## The full flow

```mermaid
sequenceDiagram
    participant C as Caller
    participant AC as AdvisoryClient
    participant R as Redactor
    participant P as AiProvider
    participant G as HallucinationGuard
    participant A as AuditRecorder
    C->>AC: advise(task, system, prompt, evidence, allowedRefs, fallback)
    AC->>R: reset didRedact; redact(prompt) + redactArray(evidence)
    alt enabled = false (default)
        AC->>A: record(Advisory: deterministic, aiUsed=false)
        AC-->>C: Advisory(text = fallback)
    else enabled = true
        AC->>P: complete(system, redactedPrompt + evidence block)
        alt transport throws
            AC->>A: record(Advisory: fallback, aiUsed=false)
            AC-->>C: Advisory(text = fallback)
        else output o returned
            AC->>G: violations(o, allowedRefs)
            alt violations ≠ ∅
                AC->>A: record(Advisory: fallback, aiUsed=true, guardPassed=false, violations)
                AC-->>C: Advisory(text = fallback)
            else clean
                AC->>R: redact(o)  (defense-in-depth)
                AC->>A: record(Advisory: safeOutput, aiUsed=true, guardPassed=true)
                AC-->>C: Advisory(text = ρ(o))
            end
        end
    end
```

## Stage 1 — mandatory redaction (pre)

Before anything else, the client **resets** the shared `Redactor`'s `didRedact` flag, then redacts the prompt
and recursively redacts the evidence. This happens **regardless of `enabled`** — even the deterministic path
records whether redaction would have fired. Nothing un-redacted is ever assembled into a prompt.
→ [PRE-prompt redaction](/concepts/redaction)

## Stage 2 — the enabled gate

```php
if (! $this->enabled()) { /* return deterministic Advisory */ }
```

If `config('iam-ai.enabled')` is false (the default), the client returns immediately with the
`deterministicFallback`, `aiUsed = false`, `guardPassed = true`, `provider = 'deterministic'`. The transport
is never touched — which is why the default is sovereign even if a provider is misconfigured.

## Stage 3 — transport

The client builds the user message as the redacted prompt plus a JSON evidence block prefixed with
"cite only these references", then calls `$this->provider->complete($system, $fullUser)`. The provider is the
abstract `AiProvider`; what's bound depends on config. **Any** thrown `Throwable` is caught and converted to a
deterministic `Advisory` (`aiUsed = false`, `provider = <name>`) — a transport failure is never an opaque
error to the user. → [Sovereign by default](/concepts/sovereign-by-default)

## Stage 4 — the hallucination guard

The raw output is checked against `allowedRefs`. A non-empty violation set discards the model text and returns
the fallback with `aiUsed = true`, `guardPassed = false`, and the offending IDs in `violations`. A clean
output proceeds. → [The hallucination guard](/concepts/hallucination-guard)

## Stage 5 — output redaction (defense-in-depth)

Only on the clean path: the model's output is **redacted again** before it is returned or audited, in case the
model reflected PII/secrets that slipped the input redaction. The returned `redacted` flag is the OR of the
input and output passes.

## Stage 6 — audit (every path)

Every branch funnels through `record()` before returning, writing `stream=ai` / `iam.ai.advisory` with the
governance metadata (and the sanitized output only if `store_outputs=true`). No `Advisory` escapes unaudited.
→ [Audit & privacy](/concepts/audit-and-privacy)

## What each branch returns

| Branch | `text` | `aiUsed` | `guardPassed` | `provider` |
| --- | --- | --- | --- | --- |
| AI disabled (default) | fallback | `false` | `true` | `deterministic` |
| Transport threw | fallback | `false` | `true` | provider name |
| Guard found violations | fallback | `true` | `false` | provider name |
| Clean success | `ρ(output)` | `true` | `true` | provider name |

## Invariants

::: collapsible "Always an Advisory"
Every path constructs and records an `Advisory`. There is no exception thrown to the caller for a transport
failure and no `null` return — the deterministic fallback guarantees a usable answer.
:::
::: collapsible "Redaction precedes transmission"
The redaction stage runs before the prompt is assembled; the transport only ever sees redacted text. The
output is redacted again before return/audit.
:::
::: collapsible "The model never decides"
On no branch does the model's output become an enforcement signal. The worst a bad output can do is be
discarded in favor of the deterministic fallback.
:::

## See also

::: grids
::: grid
::: card "Fail-safe & fallback" icon:shield-check
Every failure mode, and why each fails safe.

[Open →](/architecture/fail-safe-and-fallback)
:::
:::
::: grid
::: card "PHP API" icon:code
The exact signatures of every class.

[Open →](/reference/php-api)
:::
:::
:::
