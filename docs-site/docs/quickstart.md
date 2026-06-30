---
title: Quickstart
description: Install laravel-iam-ai, explain a PDP decision with the AI off, then opt into a sovereign provider — in four steps.
---

# Quickstart

Get from `composer require` to a working, audited explanation in a few minutes. The module is **safe and
inert** until you explicitly enable it, so step 2 works with **no provider, no network, no API key**.

::: callout tip "Nothing happens until you opt in"
After install, `enabled=false` and the transport is `disabled`. The module makes **no network calls** and
pulls in **no OpenAI dependency**. You get deterministic explanations immediately; AI-assisted ones only
after you enable a sovereign provider.
:::

## Prerequisites

- PHP **8.3+** and Laravel
- [`padosoft/laravel-iam-server`](https://doc.laravel-iam-server.padosoft.com) installed — it provides the
  `AuditRecorder` this module audits through, and the PDP whose decisions you explain.

## Four steps

::: steps
1. **Install & publish config**
   ```bash
   composer require padosoft/laravel-iam-ai
   php artisan vendor:publish --tag=laravel-iam-ai-config
   ```
   This writes `config/iam-ai.php`. The defaults are **sovereign and off**.

2. **Explain a decision (AI off)**
   `AccessExplainer` turns the PDP's decision array into a human-readable `Advisory`. It works
   deterministically out of the box:
   ```php
   use Padosoft\Iam\Ai\Modules\AccessExplainer;

   $decision = $pdp->check($query)->toArray(); // from laravel-iam-server

   $advisory = app(AccessExplainer::class)->explain($decision, 'Why was this denied?');

   echo $advisory->text;       // human-readable explanation (deterministic if AI is off)
   $advisory->citations;       // only real refs: ['dec_01H…', 'orders:refund']
   $advisory->aiUsed;          // false until you enable a provider
   $advisory->toArray();       // includes 'advisory_only' => true
   ```

3. **Opt into a sovereign provider (optional)**
   In `.env` — never wire OpenAI as a default:
   ```dotenv
   IAM_AI_ENABLED=true
   IAM_AI_PROVIDER=regolo        # sovereign EU — or 'ollama' for on-prem
   IAM_AI_MODEL=your-model
   ```
   Install the matching adapter (e.g. `padosoft/laravel-ai-regolo`); it rebinds the `AiProvider`
   transport. Redaction stays on, the guard stays on, and every call is audited.

4. **Keep enforcing with the PDP**
   The AI never gates anything. Authorization stays exactly where it was:
   ```php
   if ($pdp->check($query)->allowed) {
       // ... the PDP allowed it — the Advisory only explained it
   }
   ```
:::

## What you get back

Every call returns an immutable `Advisory`:

```php
$advisory->text;        // the explanation / draft (deterministic on any failure)
$advisory->citations;   // only identifiers present in the evidence
$advisory->aiUsed;      // was a model actually used?
$advisory->redacted;    // did redaction touch the input/output?
$advisory->guardPassed; // did the hallucination-guard approve?
$advisory->violations;  // invented identifiers the guard caught
$advisory->provider;    // 'deterministic' | 'regolo' | 'ollama' | …
$advisory->toArray();   // adds 'advisory_only' => true
```

## Next

::: grids
::: grid
::: card "Core concepts" icon:compass
Five entities, one pipeline, one promise.

[Open →](/core-concepts)
:::
:::
::: grid
::: card "Explain a denial" icon:message-circle-question
The end-to-end "Why was I denied?" guide.

[Open →](/guides/explain-a-denial)
:::
:::
::: grid
::: card "Configuration" icon:sliders
Every knob in `config/iam-ai.php`.

[Open →](/operations/configuration)
:::
:::
:::
