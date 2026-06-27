# AGENTS.md — laravel-iam-ai

Guida sintetica per agenti AI (Claude Code, Copilot, ecc.) che lavorano su questo repo. È la porta
d'ingresso: leggi questi file **prima** di scrivere codice.

## Leggi in quest'ordine
1. **`LESSON.md`** — trappole già risolte (toolchain, PHPStan max, sicurezza). Non re-imparare a tue spese.
2. **`RULES.md`** — processo single-repo, gate, invarianti di prodotto.
3. **`CLAUDE.md`** — cos'è il package, architettura reale di `src/`, invarianti specifiche.
4. **Skill** `.claude/skills/laravel-iam-package-workflow/SKILL.md` — il workflow passo-passo.

## In una riga
Modulo **opzionale, advisory-only** dell'ecosistema Laravel IAM: redaction + hallucination-guard + audit
su transport **sovrano** (Regolo UE / Ollama), **spento di default**. L'AI **non decide mai** allow/deny:
a decidere è il PDP. "Deterministic first, AI second".

## Loop advisory (NON negoziabile)
- Review advisory: `copilot -p "/review <diff vs origin/main> — focus: sicurezza, fail-closed, invarianti IAM"`.
- ⚠️ **MAI `copilot --autopilot --yolo`**: edita/commita/pusha in autonomia e ha già pushato codice
  regredito (M1). Advisory only — i fix li applichi tu, mantenendo il controllo.

## Commit & PR
- Commit terminano con: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`
- Corpo PR termina con: `🤖 Generated with [Claude Code](https://claude.com/claude-code)`

## Gate
`Pint` + `PHPStan max (larastan)` + `Pest`, in locale con **PHP 8.5 (Herd)**. Aggiorna `LESSON.md` ad ogni
scoperta/fix e tieni README + doc-site (`docs/`) coerenti col codice.
