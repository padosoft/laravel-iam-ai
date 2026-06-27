<?php

declare(strict_types=1);

namespace Padosoft\Iam\Ai;

use Padosoft\Iam\Ai\Contracts\AiProvider;
use Padosoft\Iam\Ai\Governance\HallucinationGuard;
use Padosoft\Iam\Ai\Governance\Redactor;
use Padosoft\Iam\Domain\Audit\Pii\AuditRecorder;

/**
 * Orchestratore della governance AI (doc 15 §3): ogni chiamata passa da redaction (pre) →
 * transport → hallucination-guard (post) → audit. "Deterministic first, AI second": se l'AI è
 * disabilitata (default) o se il guard boccia l'output, si ricade SEMPRE sul testo deterministico
 * costruito dai tool — l'output del modello non sostituisce mai l'evidenza, la decora soltanto.
 */
final class AdvisoryClient
{
    public function __construct(
        private readonly AiProvider $provider,
        private readonly Redactor $redactor,
        private readonly HallucinationGuard $guard,
        private readonly ?AuditRecorder $audit = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $evidence  fatti reali (dai tool) che il modello può citare
     * @param  list<string>  $allowedRefs  identificatori ammessi (anti-allucinazione)
     */
    public function advise(string $task, string $system, string $userPrompt, array $evidence, array $allowedRefs, string $deterministicFallback): Advisory
    {
        // Redaction obbligatoria PRIMA di qualsiasi uso del prompt/evidenze.
        $this->redactor->didRedact = false; // reset per-chiamata (il Redactor può essere condiviso)
        $redactedPrompt = $this->redactor->redact($userPrompt);
        $redactedEvidence = $this->redactor->redactArray($evidence);
        $didRedact = $this->redactor->didRedact;

        if (!$this->enabled()) {
            // AI spenta (default sovrano): rispondiamo in modo deterministico dai tool, advisory-only.
            return $this->record(new Advisory(
                text: $deterministicFallback,
                citations: $allowedRefs,
                aiUsed: false,
                redacted: $didRedact,
                guardPassed: true,
                provider: 'deterministic',
            ), $task);
        }

        $evidenceBlock = json_encode($redactedEvidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $fullUser = $redactedPrompt."\n\nEVIDENZE (cita solo questi riferimenti):\n".(is_string($evidenceBlock) ? $evidenceBlock : '{}');

        try {
            $output = $this->provider->complete($system, $fullUser);
        } catch (\Throwable) {
            // Transport non disponibile → fallback deterministico (mai un errore opaco all'utente).
            return $this->record(new Advisory(
                text: $deterministicFallback,
                citations: $allowedRefs,
                aiUsed: false,
                redacted: $didRedact,
                provider: $this->provider->name(),
            ), $task);
        }

        $violations = $this->guard->violations($output, $allowedRefs);
        if ($violations !== []) {
            // Output con ID inventati: NON lo si mostra; si ricade sul deterministico e si segnala.
            return $this->record(new Advisory(
                text: $deterministicFallback,
                citations: $allowedRefs,
                aiUsed: true,
                redacted: $didRedact,
                guardPassed: false,
                violations: $violations,
                provider: $this->provider->name(),
            ), $task);
        }

        // Defense-in-depth: redige anche l'OUTPUT del modello prima di restituirlo/auditarlo, nel caso
        // riflettesse PII/segreti sfuggiti alla redaction dell'input.
        $safeOutput = $this->redactor->redact($output);

        return $this->record(new Advisory(
            text: $safeOutput,
            citations: $allowedRefs,
            aiUsed: true,
            redacted: $didRedact || $this->redactor->didRedact,
            guardPassed: true,
            provider: $this->provider->name(),
        ), $task);
    }

    private function enabled(): bool
    {
        return (bool) config('iam-ai.enabled', false);
    }

    private function record(Advisory $advisory, string $task): Advisory
    {
        // Audit di OGNI azione AI (doc 15 §3.3). store_prompts=false di default: NON registriamo il
        // prompt; registriamo l'esito sanificato e i flag di governance.
        ($this->audit ?? app(AuditRecorder::class))->record([
            'stream' => 'ai',
            'event_type' => 'iam.ai.advisory',
            'metadata_json' => [
                'task' => $task,
                'provider' => $advisory->provider,
                'ai_used' => $advisory->aiUsed,
                'redacted' => $advisory->redacted,
                'guard_passed' => $advisory->guardPassed,
                'violations' => count($advisory->violations),
                'output' => (bool) config('iam-ai.store_outputs', false) ? $advisory->text : null,
            ],
        ]);

        return $advisory;
    }
}
