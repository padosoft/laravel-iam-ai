---
title: Advisory contract
description: The exact shape of the Advisory result DTO — every field, its type, what each governance flag means, the toArray() serialization with advisory_only, and the value combinations each pipeline branch produces.
---

# Advisory contract

`Padosoft\Iam\Ai\Advisory` is the single result type of the module. It is `final readonly` — immutable once
constructed — and carries the answer plus the governance metadata that lets you trust (or distrust) it.

## Fields

```php
final readonly class Advisory
{
    public function __construct(
        public string $text,
        public array  $citations = [],     // list<string>
        public bool   $aiUsed = false,
        public bool   $redacted = false,
        public bool   $guardPassed = true,
        public array  $violations = [],     // list<string>
        public string $provider = 'deterministic',
    ) {}
}
```

| Field | Type | Meaning |
| --- | --- | --- |
| `text` | `string` | The answer to show. Model output on a clean path; the **deterministic fallback** on any failure path. Always safe to display. |
| `citations` | `list<string>` | References to **real** evidence the answer may cite (e.g. `dec_01H…`, `orders:refund`). Set by the calling module from `allowedRefs`. |
| `aiUsed` | `bool` | `true` only if a model actually produced the text. `false` when AI is off, the transport threw, *or* the guard rejected the output. |
| `redacted` | `bool` | `true` if redaction fired on the input **or** the output. Mirrors `Redactor::didRedact` across both passes. |
| `guardPassed` | `bool` | `true` if the hallucination-guard found no invented identifiers. `false` ⇒ the text is the fallback, not the model's. |
| `violations` | `list<string>` | The invented identifiers the guard caught (empty when `guardPassed` is `true`). |
| `provider` | `string` | Transport identity: `deterministic` (AI off), the provider's `name()` (e.g. `regolo`, `ollama`), used for audit/telemetry. |

## Serialization

```php
public function toArray(): array;
```

Returns:

```php
[
    'text'          => string,
    'citations'     => list<string>,
    'ai_used'       => bool,
    'redacted'      => bool,
    'guard_passed'  => bool,
    'violations'    => list<string>,
    'provider'      => string,
    'advisory_only' => true,   // ALWAYS injected — the self-label
]
```

::: callout danger "`advisory_only` is always true"
`toArray()` unconditionally adds `'advisory_only' => true`. Any serialized advisory self-identifies as a
proposal, never a decision. There is no field that represents an allow/deny verdict — by design.
:::

## Field values per pipeline branch

| Branch | `text` | `aiUsed` | `guardPassed` | `violations` | `provider` |
| --- | --- | --- | --- | --- | --- |
| AI disabled (default) | fallback | `false` | `true` | `[]` | `deterministic` |
| Transport threw | fallback | `false` | `true` | `[]` | provider `name()` |
| Guard found violations | fallback | `true` | `false` | `[id, …]` | provider `name()` |
| Clean success | redacted model output | `true` | `true` | `[]` | provider `name()` |

`redacted` is orthogonal to the branch: it is `true` whenever redaction touched the input or output, on any
branch. → [The advisory pipeline](/architecture/advisory-pipeline)

## How to read it correctly

```php
$advisory = app(AccessExplainer::class)->explain($decision, 'Why?');

// Show this:
echo $advisory->text;

// Trust decisions to the PDP, not this:
$allowed = $decision['allowed'] === true;   // ✅ enforcement reads the PDP

// Use the flags for telemetry / UX, never for authorization:
if (! $advisory->aiUsed)      { /* deterministic answer — maybe label it */ }
if (! $advisory->guardPassed) { /* model invented an id; you already have the fallback */ }
if ($advisory->redacted)      { /* sensitive data was stripped from the interaction */ }
```

::: callout warning "Do not derive a verdict from an Advisory"
There is no `allowed` on `Advisory`, and `text` is prose. Read allow/deny from the PDP decision array. The
advisory's booleans are *governance* signals (`aiUsed`, `redacted`, `guardPassed`), not authorization.
:::

## JSON example

```json
{
  "text": "Accesso NEGATO (decision dec_01H…). Nessun grant corrispondente per orders:refund.",
  "citations": ["dec_01H…", "orders:refund"],
  "ai_used": false,
  "redacted": true,
  "guard_passed": true,
  "violations": [],
  "provider": "deterministic",
  "advisory_only": true
}
```

## See also

- [PHP API](/reference/php-api)
- [Advisory-only authorization](/concepts/advisory-only)
- [Keep the AI out of the decision](/best-practices/keep-ai-out-of-decisions)
