---
title: Home
description: Optional AI module for Laravel IAM — advisory-only governance on a sovereign transport, off by default.
---

# Laravel IAM — AI

`padosoft/laravel-iam-ai` is the **optional AI module** of the
[Laravel IAM](https://github.com/padosoft) ecosystem. It adds natural-language **explanations**,
least-privilege **suggestions** and access-review **summaries** — without ever letting a model make an
authorization decision.

::: callout warning "Advisory only — the PDP decides"
Every output of this module is an `Advisory`: a proposal flagged `advisory_only`. The deterministic
**Policy Decision Point** in [`laravel-iam-server`](https://github.com/padosoft/laravel-iam-server) is the
only authority over allow/deny. The AI decorates evidence; it never replaces it.
:::

## What it gives you

- **`AccessExplainer`** — rephrases the PDP's `explanation[]` in plain language, citing only real refs.
- **`Redactor`** — strips secrets and PII before *and* after the model call.
- **`HallucinationGuard`** — rejects any answer that cites an identifier not in the evidence.
- **`AdvisoryClient`** — orchestrates redaction → transport → guard → audit, with a deterministic fallback.

## Safe by default

Out of the box the module is **disabled** and the transport is `disabled` — no data leaves your perimeter.
When you opt in, the recommended providers are **Regolo (EU)** or **Ollama (on-prem)**. OpenAI is never a
default.

## Next

- [Getting started](getting-started.md) — install and explain your first decision.
- [Concepts](concepts.md) — the governance pipeline and why the AI can't decide.
- [Configuration](configuration.md) — enable, choose a sovereign provider, tune redaction.
- [Reference](reference.md) — every class and its signature.
