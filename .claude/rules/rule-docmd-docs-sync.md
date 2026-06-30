# RULE — keep the docmd docs-site in sync (binding)

**This rule is mandatory and blocking.** Whenever you add or change a **user-facing feature** of
laravel-iam-ai, or update the README in a substantive way, you **MUST** update the corresponding docmd page
under `docs-site/docs/**` in the **same** unit of work — following the `docmd-docs` skill.

## When it applies (you MUST update the docs-site)

- A new or changed **governance behavior** — `AdvisoryClient` pipeline order, fallback/fail-safe semantics,
  the `Redactor` patterns, the `HallucinationGuard` recognizers/whitelist rule → update the matching
  `docs-site/docs/concepts/**` and `docs-site/docs/architecture/**` page(s).
- A new or changed **module** on top of `AdvisoryClient` (like `Modules\AccessExplainer`) → add/update a
  guide under `docs-site/docs/guides/**` and the `docs-site/docs/reference/php-api.md` entry.
- A change to the **`Advisory` shape** (fields, `toArray()`, `advisory_only`) → update
  `docs-site/docs/reference/advisory-contract.md`.
- A change to the **transport seam** (`Contracts\AiProvider`, `Providers\DisabledProvider`, how
  `IamAiServiceProvider` resolves the provider, a new sovereign adapter) → update
  `docs-site/docs/concepts/sovereign-by-default.md` and `docs-site/docs/guides/write-a-provider-adapter.md`.
- A new or changed **config key** (`config/iam-ai.php`) or **env var** → update
  `docs-site/docs/operations/configuration.md` (and the reference defaults list).
- A change to the **audit record** (`stream`, `event_type`, `metadata_json` fields) → update
  `docs-site/docs/concepts/audit-and-privacy.md` and `docs-site/docs/operations/observability.md`.
- A substantive **README** change (features, quick-start, ecosystem) → reflect it in the relevant page(s).

A **new page** MUST also be registered in `navigation[]` in `docmd.config.json`, or it will not appear in the
sidebar.

## When it does NOT apply (state it explicitly in the PR/changelog)

Internal refactors with no behavior change, test-only changes, tooling/CI fixes, or pure cosmetics. If you
skip a docs update, say so and why in the PR description or changelog.

## Definition of done (blocking)

1. The matching `docs-site/docs/**` page(s) reflect reality — real class/method/config names, the real audit
   shape (`stream=ai`, `iam.ai.advisory`), and the **advisory-only / sovereign-by-default** invariants.
2. New pages are in `navigation[]`.
3. From `docs-site/`: **`npm run check` and `npm run build` are green**, and `_site/index.html` exists.

## Anti-patterns (reject in review)

- A user-facing feature shipped with no docs-site update.
- A page added but missing from `navigation[]`.
- MDX/JSX or raw HTML tags, or `::: button` (the guard fails the build).
- Documenting the AI as if it **decides** access, or wiring OpenAI as a default — both contradict the
  advisory-only / sovereign-by-default design.
- Inventing classes/methods/config that don't exist — accuracy is non-negotiable.
