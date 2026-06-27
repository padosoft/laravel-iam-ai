# CLAUDE.md — laravel-iam-ai

Guida per agenti AI che lavorano in questo repo (package dell'ecosistema **Laravel IAM**). Prima di
qualsiasi lavoro leggi `LESSON.md`, `RULES.md` e questa pagina. Skill: `laravel-iam-package-workflow`.

## Cos'è questo package

Modulo **opzionale** AI di Laravel IAM: governance **advisory-only** (redaction + hallucination-guard +
audit) su un transport **sovrano**. Default Regolo (UE) / Ollama (on-prem), **MAI OpenAI di default**.

- **Composer:** `padosoft/laravel-iam-ai`
- **Namespace:** `Padosoft\Iam\Ai\`
- **Ruolo nell'ecosistema:** layer AI **advisory**. **Non decide MAI allow/deny** — produce solo
  bozze/spiegazioni che il PDP deterministico continua ad adjudicare. **Spento di default**
  (`enabled=false`), transport **sovrano** di default. "Deterministic first, AI second".
- **Dipende da:** `padosoft/laravel-iam-contracts` + `padosoft/laravel-iam-server`
  (`Padosoft\Iam\Domain\Audit\Pii\AuditRecorder`). Suggerisce `laravel/ai` e un provider sovrano
  (`padosoft/laravel-ai-regolo`); **non richiede mai OpenAI**.

## Architettura del package

Tutto ruota attorno a una pipeline di governance: **redaction (pre) → transport → hallucination-guard
(post) → audit**. Sottocartelle di `src/` (namespace `Padosoft\Iam\Ai\…`):

- **`AdvisoryClient`** (radice) — orchestratore della governance. `advise(task, system, userPrompt,
  evidence, allowedRefs, deterministicFallback): Advisory`. Resetta `Redactor::didRedact`, redige
  prompt+evidenze, e: se l'AI è spenta (default) o il transport lancia o il guard boccia, **ricade
  SEMPRE sul testo deterministico**. Redige anche l'**output** (defense-in-depth). Audita ogni
  chiamata (`stream=ai`, `event_type=iam.ai.advisory`); `store_prompts=false`, `store_outputs` opt-in.
- **`Advisory`** (`final readonly`) — l'esito: `text`, `citations`, `aiUsed`, `redacted`, `guardPassed`,
  `violations`, `provider`. `toArray()` aggiunge sempre `advisory_only => true`.
- **`Governance/Redactor`** — pipeline di redaction PRE-prompt: Bearer/Basic, JWT, PEM private key,
  `password|secret|token|otp|cookie|session_id` espliciti, email, IPv4, hex lunghi, blob base64.
  Fail-safe: nel dubbio redige. `public bool $didRedact` + `@phpstan-impure` su `redact()`/`redactArray()`.
- **`Governance/HallucinationGuard`** — `violations(output, allowedRefs)`: l'output può citare SOLO
  identificatori presenti nelle evidenze. Riconosce ID con prefisso (`dec_`/`grn_`/`decision-…`), ULID
  nudi (26 char) e UUID. Un ID inventato → violazione → advisory marcato non affidabile.
- **`Modules/AccessExplainer`** — Policy Copilot (doc 15 §4): riformula in linguaggio naturale
  l'`explanation[]` del PDP citando solo evidenze reali. Fail-closed: solo `allowed === true` (bool)
  conta come CONSENTITO. Con AI spenta resta utile (composizione deterministica).
- **`Contracts/AiProvider`** — transport astratto (`name()`, `complete(system, user)`). Gli adapter
  reali (Regolo/Ollama via `laravel/ai`) lo implementano in package opzionali.
- **`Providers/DisabledProvider`** — provider di default (AI spenta): nessuna chiamata di rete; se
  invocato lancia, e l'`AdvisoryClient` ricade sul deterministico. Out-of-the-box **nessun dato esce**.
- **`IamAiServiceProvider`** — registra la governance e risolve `AiProvider` da `iam-ai.provider`
  (default `DisabledProvider`). Gli adapter ridefiniscono il binding quando installati.
- **`config/iam-ai.php`** — default **sovrano e spento**: `enabled=false`, `provider=disabled`,
  `redaction=true`, `store_prompts=false`, `store_outputs=false`, `max_context_events=50`.

I docblock citano il doc di design `laravel-iam-docs/15` (modulo AI).

## Invarianti (NON violare)
1. **Mai bypassare il PDP.** L'AI propone draft/spiegazioni; il PDP deterministico decide allow/deny.
2. **Fail-closed** sull'autorizzazione; mai fail-open su operazioni critiche.
3. **Niente segreti/OTP/PII nei log.** Segreti cifrati via envelope encryption.
4. **Audit per ogni mutazione** (hash-chain).
5. **Slug permessi/ruoli immutabili** (`app_key:permission`).
6. **Scope/condition dichiarati dalle app** nel manifest, mai hardcoded nel core.
7. **Nessuna UI legge il DB**: solo Admin API.
8. **OIDC layer**: base MIT (steverhoades). **Vietato** codice AGPL (limosa-io). OAuth = league/oauth2-server.

### Specifiche di questo package
- **AI advisory-only** (invariante di prodotto #1): l'output del modello **decora** l'evidenza, non la
  sostituisce, e **non decide mai** allow/deny. Ogni `Advisory` è una proposta; a decidere è il PDP.
- **Default sovrano e spento**: `enabled=false`, `provider=disabled`. Nessun dato lascia il perimetro
  finché non si sceglie esplicitamente un provider sovrano (Regolo UE / Ollama). **Mai OpenAI di default.**
- **Redaction obbligatoria PRIMA di ogni chiamata** e di nuovo sull'output (defense-in-depth). Nessun
  segreto/PII raggiunge il modello né l'audit (`store_prompts=false`).
- **`@phpstan-impure` su `redact()`/`redactArray()`**: mutano `$didRedact` (side-effect osservabile) e
  vengono chiamati due volte; senza l'attributo PHPStan crede il secondo valore immutato
  (`booleanOr.leftAlwaysFalse`).
- **Hallucination-guard come failsafe del PDP**: impedisce all'AI di "dire sì/no" o citare prove
  inesistenti. Aggiungere un nuovo formato di ID interno → aggiornare i pattern del guard.

## Convenzioni codice
- `declare(strict_types=1)`, classi `final` di default.
- Namespace radice **`Padosoft\Iam\`** (PSR-4).
- **PHPStan max**, **Pest**, **Pint**. Test negativi obbligatori (AI spenta → deterministico; guard
  boccia ID inventato; redaction su segreti/PII; transport che lancia → fallback).

## Gate (in locale, con PHP 8.5 Herd)
```bash
# in un progetto root con questo package installato via path/VCS + le sue dev-deps
php vendor/bin/pint
php vendor/bin/phpstan analyse --memory-limit=1G
php vendor/bin/pest
```
> Nota: i test e il tooling QA sono stati sviluppati nel monorepo originale; vedi `LESSON.md` per il
> setup standalone. La suite di test completa di questo package è in fase di migrazione per-repo.

## Loop di lavoro
Branch per task → gate locale (test + advisory `copilot -p`, **mai `--yolo`**) → PR → CI + Copilot review
→ merge → tag. Aggiorna `LESSON.md` ad ogni fix. Dettaglio: la skill `laravel-iam-package-workflow`.
