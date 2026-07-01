<p align="center">
  <img src="art/banner.png" alt="Laravel IAM" width="100%">
</p>

<h1 align="center">Laravel IAM — AI</h1>

<p align="center">
  <strong>AI that <em>assists</em> your access governance — and never holds the decision.</strong><br>
  Advisory-only redaction, hallucination-guard and audit, on a sovereign EU / on-prem transport. Off by default.
</p>

<p align="center">
  <a href="https://github.com/padosoft/laravel-iam-ai/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/padosoft/laravel-iam-ai/tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://packagist.org/packages/padosoft/laravel-iam-ai"><img src="https://img.shields.io/packagist/v/padosoft/laravel-iam-ai.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/padosoft/laravel-iam-ai"><img src="https://img.shields.io/packagist/dt/padosoft/laravel-iam-ai.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/padosoft/laravel-iam-ai"><img src="https://img.shields.io/packagist/php-v/padosoft/laravel-iam-ai.svg?style=flat-square" alt="PHP Version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square" alt="License"></a>
</p>

<p align="center">
  <strong><a href="https://doc.laravel-iam-ai.padosoft.com">📚 Documentation</a></strong> ·
  <a href="https://doc.laravel-iam-ai.padosoft.com/quickstart">Quickstart</a> ·
  <a href="https://doc.laravel-iam-ai.padosoft.com/concepts/advisory-only">Advisory-only</a> ·
  <a href="https://packagist.org/packages/padosoft/laravel-iam-ai">Packagist</a>
</p>

---

> [!WARNING]
> **Advisory only.** This module never grants or denies access. It produces *explanations* and *drafts*
> that a human reviews and that the deterministic **PDP** (in `laravel-iam-server`) still adjudicates.
> The AI decorates evidence — it never replaces it, and it never decides.

## Why this package

Bolting an LLM onto an IAM system is tempting and dangerous: models hallucinate identifiers, leak secrets
into prompts, and — worst of all — get trusted to *make the call*. `laravel-iam-ai` gives you the useful
half (natural-language explanations, least-privilege suggestions, access-review summaries) while making
the dangerous half **structurally impossible**:

- **The PDP, not the AI, decides.** Every AI output is an `Advisory` — a proposal, flagged `advisory_only`.
- **Nothing leaks.** A mandatory redaction pipeline strips tokens, keys, passwords, OTPs, emails and IPs
  *before* any prompt leaves the process — and again on the model's output (defense-in-depth).
- **No invented evidence.** A hallucination-guard rejects any answer that cites an ID not present in the
  real evidence; the response falls back to deterministic text.
- **Sovereign by default, off by default.** Out of the box `enabled=false` and the transport is
  `disabled` — no data leaves your perimeter. When you turn it on, the recommended providers are
  **Regolo (EU)** or **Ollama (on-prem)**. **OpenAI is never a default.**

"Deterministic first, AI second": if the AI is off, the transport fails, or the guard rejects the output,
you always get the deterministic answer built from your tools.

## Features

- **`AdvisoryClient`** — the governance orchestrator: `redaction (pre) → transport → hallucination-guard
  (post) → audit`, with a deterministic fallback on every failure path.
- **`Redactor`** — mandatory PII/secret redaction (Bearer/JWT/PEM keys, `password|secret|token|otp|cookie`,
  email, IPv4, long hex & base64 blobs). Fail-safe: when in doubt, redact.
- **`HallucinationGuard`** — the output may cite only identifiers found in the evidence (prefixed IDs,
  ULIDs, UUIDs). Anything invented is a violation.
- **`AccessExplainer`** — a Policy Copilot that rephrases the PDP's `explanation[]` in plain language,
  citing only real `decision_id` / grants. Fail-closed: only a true boolean `allowed` reads as *allowed*.
- **`DisabledProvider`** — the safe default transport: makes no network calls; the client falls back to
  deterministic text.
- **Tamper-evident audit** of every AI action (`stream=ai`), with `store_prompts=false` by default.

## Use cases

- **"Why was I denied?"** Turn a terse PDP `explanation[]` into a clear sentence for a support agent or
  end user — without ever claiming the decision *should* have been different.
- **Draft a least-privilege role.** Let the AI propose a tightened role from observed usage; a human
  approves it and the PDP enforces it.
- **Summarize an access review.** Condense a campaign's signals into a digest for the reviewer — advisory,
  fully audited, with no secrets in the trail.

## Installation

```bash
composer require padosoft/laravel-iam-ai
```

**Requirements:** PHP **8.3+**, Laravel, and `padosoft/laravel-iam-server` (provides the audit recorder).

> [!NOTE]
> **Safe by default.** After install the module is **disabled** (`enabled=false`) and the transport is
> `disabled` — it does nothing until you opt in and pick a sovereign provider. No OpenAI dependency is
> ever pulled in; real providers (Regolo/Ollama) are optional adapters you add explicitly.

## Quick start

### 1. Publish the config

```bash
php artisan vendor:publish --tag=laravel-iam-ai-config
```

### 2. Explain a decision (works even with the AI off)

`AccessExplainer` takes the PDP decision array and returns an `Advisory`:

```php
use Padosoft\Iam\Ai\Modules\AccessExplainer;

$decision = $pdp->check($query)->toArray(); // from laravel-iam-server

$advisory = app(AccessExplainer::class)->explain($decision, 'Why was this denied?');

$advisory->text;          // human-readable explanation (deterministic if AI is off)
$advisory->citations;     // only real refs: ['dec_01H…', 'orders:refund']
$advisory->aiUsed;        // false until you enable a provider
$advisory->toArray();     // includes 'advisory_only' => true
```

### 3. Turn on a sovereign provider (opt-in)

Two transports ship with the package — **Regolo** (EU, OpenAI-compatible) and **Ollama** (on-prem). Just
configure one in `.env`; no OpenAI is ever wired.

```dotenv
# Regolo (EU sovereign, OpenAI-compatible)
IAM_AI_ENABLED=true
IAM_AI_PROVIDER=regolo
IAM_AI_BASE_URL=https://api.regolo.ai/v1
IAM_AI_API_KEY=your-regolo-api-key
IAM_AI_MODEL=your-model
IAM_AI_TIMEOUT=20
```

```dotenv
# …or Ollama (on-prem, your infra)
IAM_AI_ENABLED=true
IAM_AI_PROVIDER=ollama
IAM_AI_BASE_URL=http://localhost:11434
IAM_AI_MODEL=llama3
# IAM_AI_API_KEY=...   # only if Ollama sits behind an authenticating gateway
```

**Fail-safe:** if the transport isn't fully configured (e.g. `regolo` without an API key), the module
silently stays on `DisabledProvider` — never a misconfigured network call. Redaction stays on, the
hallucination guard stays on, every call is audited, and any transport error falls back to the
deterministic text. Need a different backend? Implement `AiProvider` — see
[Write a provider adapter](https://doc.laravel-iam-ai.padosoft.com/guides/write-a-provider-adapter).

### 4. The PDP still decides

The AI never gates anything. Authorization stays exactly where it was:

```php
if ($pdp->check($query)->allowed) {
    // ... the PDP allowed it — the Advisory only explained it
}
```

## Ecosystem

| Package | Role |
| --- | --- |
| [laravel-iam-contracts](https://github.com/padosoft/laravel-iam-contracts) | Shared interfaces & DTOs — the dependency root |
| [laravel-iam-server](https://github.com/padosoft/laravel-iam-server) | The IAM server: identity, PDP, OAuth/OIDC, audit, governance, Admin API & panel |
| [laravel-iam-client](https://github.com/padosoft/laravel-iam-client) | Client for apps consuming Laravel IAM: OIDC login, JWT/JWKS, middleware, Gate adapter |
| **laravel-iam-ai** *(this repo)* | Optional AI module: advisory-only governance (redaction + hallucination guard + audit) |
| [laravel-iam-directory](https://github.com/padosoft/laravel-iam-directory) | Optional directory module: LDAP / Active Directory (LdapRecord); SCIM in v2 |
| [laravel-iam-bridge-spatie-permission](https://github.com/padosoft/laravel-iam-bridge-spatie-permission) | Migration bridge from spatie/laravel-permission: scan, shadow mode, cutover |

## Documentation

Full documentation lives at **[doc.laravel-iam-ai.padosoft.com](https://doc.laravel-iam-ai.padosoft.com)** —
a docmd doc-site (source in [`docs-site/`](docs-site/)) with theory, mermaid diagrams, ADRs and a complete PHP
API reference. Good entry points:

- [Quickstart](https://doc.laravel-iam-ai.padosoft.com/quickstart) and
  [Core concepts](https://doc.laravel-iam-ai.padosoft.com/core-concepts)
- Concepts & theory: [Advisory-only](https://doc.laravel-iam-ai.padosoft.com/concepts/advisory-only),
  [Sovereign by default](https://doc.laravel-iam-ai.padosoft.com/concepts/sovereign-by-default),
  [PRE-prompt redaction](https://doc.laravel-iam-ai.padosoft.com/concepts/redaction),
  [The hallucination guard](https://doc.laravel-iam-ai.padosoft.com/concepts/hallucination-guard)
- Architecture: [the advisory pipeline](https://doc.laravel-iam-ai.padosoft.com/architecture/advisory-pipeline)
  and [ADRs](https://doc.laravel-iam-ai.padosoft.com/architecture/decisions)
- Reference: [PHP API](https://doc.laravel-iam-ai.padosoft.com/reference/php-api) and the
  [Advisory contract](https://doc.laravel-iam-ai.padosoft.com/reference/advisory-contract)

A lightweight in-repo copy also lives in [`docs/`](docs/) ([index](docs/index.md)).

## Security

This module is **fail-closed and privacy-first by design**: redaction is mandatory and runs before *and*
after the model, prompts are never stored, the hallucination-guard rejects unsupported claims, and the
default transport makes no network calls. The AI is advisory-only — it can never escalate access. If you
discover a security issue, please email **security@padosoft.com** rather than opening a public issue.

## License

MIT © [Padosoft](https://www.padosoft.com). See [LICENSE](LICENSE).
