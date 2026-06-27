# LESSON.md — lezioni dell'ecosistema Laravel IAM

> Lezioni **generali** valide per ogni package, accumulate costruendo Laravel IAM v1.0 (16 milestone,
> TDD + loop advisory). Sotto, la sezione **specifica di questo package**. Aggiorna ad ogni scoperta.

## Generali — toolchain & PHPStan max

- **Test con PHP 8.5 (Herd)**: `~/.config/herd/bin/php85/php.exe`. Su Windows, PHPStan vuole
  `--memory-limit=1G` e, prima di Pest/testbench, `attrib -R` sulla dir
  `vendor/orchestra/testbench-core/laravel/bootstrap/cache` (bug `is_writable()`). `.gitattributes eol=lf`.
- **PHPStan crash transitorio** ("Result is incomplete because of severe errors"): ri-eseguire risolve.
- **Mai cast su `mixed`**: usare guardie `is_int`/`is_string`/`is_numeric`, non `(string)`/`(int)`.
- **`@property` sui Model invece di castare nel chiamante**: una colonna castata letta da un servizio
  esterno al model fa fallire PHPStan (`property.notFound` → `Cannot cast mixed`). Dichiarare
  `@property Carbon|null` sul model; poi un `?->` su valore ora non-null diventa `nullsafe.neverNull` → `->`.
- **Mai `*/` dentro un docblock**: `decided_*/granted_id` in `/** */` CHIUDE il commento → ParseError.
- **`@phpstan-impure`** per i metodi con side-effect osservabili (mutano una proprietà pubblica e vengono
  chiamati due volte): senza, PHPStan crede il secondo valore immutato (`booleanOr.leftAlwaysFalse`).
- **Config da `mixed` → `array<string,mixed>` provabile**: `is_array($x) ? $x : []` resta `array<mixed>`;
  ricostruire con un `foreach` che casta le chiavi a stringa per soddisfare la firma.
- **larastan + generics Eloquent + closure**: `Builder<User>` non è assegnabile a `Builder<Model>`
  (invariante) e `get()` perde `TModel`. Per un paginator generico: `@param Builder<covariant Model>` +
  `callable(Model): array` con narrowing `instanceof` al call-site.

## Generali — sicurezza & processo

- **Fail-closed sempre**: default-deny, deny-overrides; un errore (transport, PDP, parsing) → deny, mai un
  allow né un 500 opaco. Vale per PDP, client, directory, AI.
- **Il loop advisory trova bug reali ad ogni slice**: TOCTOU, fail-open, takeover, info-disclosure,
  escalation. `copilot -p` (advisory), **mai** `--autopilot --yolo`. Ogni fix → qui.
- **TOCTOU sulle transizioni di stato**: leggere-poi-scrivere uno stato senza `DB::transaction` +
  `lockForUpdate` + re-check sotto lock = last-write-wins (grant orfano, doppia approvazione).
- **Snapshot vs dato vivo**: la governance congela i segnali/policy al momento giusto; l'esito non deve
  dipendere da una modifica successiva (un ruolo tolto dal catalogo non deve creare grant permanenti).
- **Tenant isolation = 404, non 403**: il cross-tenant deve essere indistinguibile da "non esiste",
  altrimenti il 403 conferma l'esistenza dell'UUID (enumerazione).
- **Deps pesanti in `suggest`, non `require`**: `aws-sdk-php`, `ldaprecord` (ext-ldap), `laravel/ai`
  rallentano/ rompono install e CI. Il core resta usabile senza; l'adapter reale è opzionale e, se non
  installabile in dev, va isolato (sottospazio + `excludePaths` PHPStan).
- **Commit message via file** se l'here-string fallisce su Windows: scrivere su file e `git commit -F`.

## Specifiche di questo package

- **AI advisory-only è un'invariante, non un default**: l'`AdvisoryClient` ricade sul testo
  deterministico in TRE casi — AI spenta, transport che lancia, guard che boccia. L'output del modello
  non sostituisce mai l'evidenza: la decora. Non esiste un percorso in cui l'AI "decide".
- **`@phpstan-impure` su `Redactor::redact()`/`redactArray()`**: mutano `$didRedact` e vengono invocati
  due volte per chiamata (input poi output, defense-in-depth). Senza l'attributo, PHPStan considera la
  seconda lettura sempre `false` → `booleanOr.leftAlwaysFalse`. Resettare `didRedact=false` per-chiamata
  perché il `Redactor` può essere condiviso (singleton).
- **Redaction PRIMA e DOPO**: redigere l'input prima di chiamare il modello e di nuovo l'output prima di
  mostrarlo/auditarlo — il modello potrebbe riflettere segreti/PII sfuggiti. Fail-safe: nel dubbio si
  redige (meglio un prompt meno ricco di un leak). `store_prompts=false`: i prompt non si persistono mai.
- **Hallucination-guard = i pattern di ID vanno tenuti completi**: prefissati (`xxx_…`/`xxx-…`), ULID
  nudi (26 char), UUID. Un nuovo formato di ID interno non coperto rende il guard aggirabile (il modello
  può inventare un riferimento in quel formato). Ordine dei pattern di redaction: hex PRIMA del base64,
  altrimenti il base64 spezza l'hex.
- **Sovrano e spento di default**: `enabled=false`, `provider=disabled`, `DisabledProvider` lancia se
  invocato. Out-of-the-box nessun dato esce dal perimetro; i provider reali (Regolo/Ollama) sono adapter
  opzionali che ridefiniscono il binding `AiProvider`. **Mai cablare OpenAI come default.**
- **Dipendenza dal server per l'audit**: `AuditRecorder` vive in `-server`. L'audit dell'AI usa
  `stream=ai` ed è separato dall'audit di autorizzazione, ma condivide la hash-chain tamper-evident.
