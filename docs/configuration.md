---
title: Configuration
description: Every knob in config/iam-ai.php — enabling the module, choosing a sovereign provider, and redaction.
---

# Configuration

Publish the config first:

```bash
php artisan vendor:publish --tag=laravel-iam-ai-config
```

This writes `config/iam-ai.php`. The defaults are **sovereign and off**.

## Keys

| Key | Env | Default | Meaning |
| --- | --- | --- | --- |
| `enabled` | `IAM_AI_ENABLED` | `false` | Master switch. While `false`, the `DisabledProvider` is used and you get deterministic answers only. |
| `provider` | `IAM_AI_PROVIDER` | `disabled` | `disabled` \| `regolo` (EU) \| `ollama` (on-prem). Real providers are optional adapters. |
| `model` | `IAM_AI_MODEL` | `null` | Model name passed to the chosen provider. |
| `redaction` | — | `true` | The pre-prompt redaction pipeline. Mandatory; leave it on. |
| `store_prompts` | — | `false` | Never persist prompts (possible PII/secrets). |
| `store_outputs` | `IAM_AI_STORE_OUTPUTS` | `false` | Opt-in: store the **sanitized** output in the audit trail. |
| `max_context_events` | — | `50` | Cap on past events passed as model context. |

## Enabling a sovereign provider

::: callout warning "Never default to OpenAI"
The recommended providers are sovereign: **Regolo** (Italian/EU) or **Ollama** (on-prem). The core never
wires a non-sovereign provider as a default, and pulls in no AI SDK via `require`.
:::

::: tabs
== tab "Regolo (EU)" icon:cloud
```dotenv
IAM_AI_ENABLED=true
IAM_AI_PROVIDER=regolo
IAM_AI_MODEL=your-model
```
Install the adapter:
```bash
composer require padosoft/laravel-ai-regolo
```
== tab "Ollama (on-prem)"
```dotenv
IAM_AI_ENABLED=true
IAM_AI_PROVIDER=ollama
IAM_AI_MODEL=llama3.1
```
Point your transport adapter at your local Ollama endpoint.
:::

The adapter rebinds the `Padosoft\Iam\Ai\Contracts\AiProvider` binding. Redaction and the
hallucination-guard remain active regardless of provider.

## Audit & privacy

- Every AI action is audited under `stream=ai`, event `iam.ai.advisory`, with the governance flags
  (`ai_used`, `redacted`, `guard_passed`, `violations`).
- `store_prompts=false` is a hard default — prompts are never written.
- With `store_outputs=true`, only the **redacted** output is persisted.
