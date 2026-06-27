---
title: Concepts
description: The governance pipeline behind laravel-iam-ai and why the AI can never make an authorization decision.
---

# Concepts

## The problem

LLMs are great at *explaining* and *drafting*, and terrible at being *trusted*. Wire one into an IAM
system naively and three things go wrong:

1. It **hallucinates** — citing a `decision_id` or grant that never existed.
2. It **leaks** — your prompt carries a Bearer token, a private key, an email, and now so do the
   provider's logs.
3. It **decides** — a developer reads "the user should have access" and ships it as an authorization path.

## The mental model

`laravel-iam-ai` treats the model as an **untrusted advisor behind a governance gate**. Everything flows
through one pipeline:

```
redaction (pre)  →  transport  →  hallucination-guard (post)  →  audit
```

…and the whole thing is wrapped in **"deterministic first, AI second"**: if the AI is off, the transport
throws, or the guard rejects the output, you get the deterministic answer built from your tools. There is
no code path where the model's word becomes the decision.

::: callout danger "The AI never decides"
Authorization lives entirely in the PDP (`laravel-iam-server`). This module only ever returns an
`Advisory` — a proposal flagged `advisory_only`. A human (or the PDP) acts on it.
:::

## Core entities

- **`AdvisoryClient`** — the orchestrator. Resets the redactor, redacts the prompt and evidence, calls the
  transport, runs the guard, redacts the output again, and audits. Returns an `Advisory` on every path.
- **`Redactor`** — deterministic, mandatory, fail-safe. Runs **before** the prompt leaves and **again** on
  the output (defense-in-depth). Tracks whether it touched anything via `didRedact`.
- **`HallucinationGuard`** — extracts the identifiers in the output and rejects any that aren't in the
  `allowedRefs` taken from real evidence.
- **`AiProvider` / `DisabledProvider`** — the transport seam. The default makes no network calls; sovereign
  adapters (Regolo/Ollama) rebind it.
- **`Advisory`** — the immutable result, carrying the text, the citations, and governance flags
  (`aiUsed`, `redacted`, `guardPassed`, `violations`).

## Example

```php
$decision = $pdp->check($query)->toArray();
$advisory = app(AccessExplainer::class)->explain($decision, 'Why was this denied?');

if (! $advisory->guardPassed) {
    // the model cited an invented id — we already fell back to deterministic text
}
echo $advisory->text;
```

## Anti-patterns

- ❌ **Gating on AI output.** `if ($advisory->...) { grant() }` — never. Gate on `$pdp->check()->allowed`.
- ❌ **Sending raw context to the model.** Always go through `AdvisoryClient`, which redacts first.
- ❌ **Wiring OpenAI as a default.** The default is sovereign and off. A provider is an explicit opt-in.
- ❌ **Storing prompts.** `store_prompts=false` is a privacy guarantee, not a tunable to flip casually.

## Why this design

Because the dangerous capability — *deciding* — is removed structurally, not by convention. Redaction and
the guard are mandatory and on the critical path, the default transport is inert, and the output type is
literally named `Advisory`. The useful half of AI ships; the unsafe half cannot.
