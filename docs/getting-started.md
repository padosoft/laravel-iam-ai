---
title: Getting started
description: Install laravel-iam-ai, explain a decision with the AI off, then opt into a sovereign provider.
---

# Getting started

## Requirements

- PHP **8.3+** and Laravel
- [`padosoft/laravel-iam-server`](https://github.com/padosoft/laravel-iam-server) — provides the
  `AuditRecorder` the module audits through
- [`padosoft/laravel-iam-contracts`](https://github.com/padosoft/laravel-iam-contracts) (pulled in transitively)

## Install

```bash
composer require padosoft/laravel-iam-ai
```

::: callout tip "Nothing happens until you opt in"
After install, `enabled=false` and the transport is `disabled`. The module makes **no network calls** and
pulls in **no OpenAI dependency**. You get deterministic explanations immediately, and AI-assisted ones
only after you explicitly enable a sovereign provider.
:::

## Steps

::: steps
1. **Publish the config**
   ```bash
   php artisan vendor:publish --tag=laravel-iam-ai-config
   ```
2. **Explain a decision (AI off)**
   `AccessExplainer` works deterministically out of the box:
   ```php
   use Padosoft\Iam\Ai\Modules\AccessExplainer;

   $decision = $pdp->check($query)->toArray();
   $advisory = app(AccessExplainer::class)->explain($decision, 'Why was this denied?');

   echo $advisory->text;     // deterministic explanation, real citations only
   $advisory->aiUsed;        // false
   ```
3. **Opt into a sovereign provider (optional)**
   Set `IAM_AI_ENABLED=true` and `IAM_AI_PROVIDER=regolo` (EU) or `ollama` (on-prem), then install the
   matching adapter package. Redaction and the hallucination-guard stay on automatically.
4. **Keep enforcing with the PDP**
   The AI never gates anything — authorization stays in `laravel-iam-server`:
   ```php
   if ($pdp->check($query)->allowed) { /* ... */ }
   ```
:::

## What you get back

Every call returns an `Advisory`:

```php
$advisory->text;        // the explanation / draft (deterministic on any failure)
$advisory->citations;   // only identifiers present in the evidence
$advisory->aiUsed;      // was a model actually used?
$advisory->redacted;    // did redaction touch the input/output?
$advisory->guardPassed; // did the hallucination-guard approve?
$advisory->toArray();   // adds 'advisory_only' => true
```

## Next

- [Concepts](concepts.md) — how the pipeline guarantees the AI can't decide.
- [Configuration](configuration.md) — every knob in `config/iam-ai.php`.
