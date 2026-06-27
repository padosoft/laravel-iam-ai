<?php

declare(strict_types=1);

namespace Padosoft\Iam\Ai\Modules;

use Padosoft\Iam\Ai\Advisory;
use Padosoft\Iam\Ai\AdvisoryClient;

/**
 * Policy Copilot / Access Explainer (doc 15 §4): riformula in linguaggio naturale l'`explanation[]`
 * prodotto dal PDP (doc 09), citando SOLO le evidenze reali (decision_id, grant/role coinvolti).
 * L'AI non decide nulla: spiega una decisione già presa dal PDP. Con AI spenta, la spiegazione è
 * comunque utile (composizione deterministica dell'explanation).
 */
final class AccessExplainer
{
    private const SYSTEM = 'Sei un assistente di sicurezza che spiega, in italiano e in modo conciso, '
        .'perché un accesso è stato consentito o negato. Cita SOLO gli identificatori presenti nelle evidenze. '
        .'NON inventare ID, ruoli o eventi. NON dire se l\'accesso "dovrebbe" essere consentito: a decidere è il PDP.';

    public function __construct(private readonly AdvisoryClient $client) {}

    /**
     * @param  array<string, mixed>  $decision  output del PDP (toArray): allowed, decision_id, explanation[], matched[]
     */
    public function explain(array $decision, string $question = ''): Advisory
    {
        // Fail-closed: solo un boolean `true` vero conta come consentito. Una stringa "false"
        // (truthy in PHP) o un valore inatteso ricade su NEGATO, mai su un "CONSENTITO" spurio.
        $allowed = ($decision['allowed'] ?? false) === true;
        $decisionId = is_string($decision['decision_id'] ?? null) ? $decision['decision_id'] : '';
        $explanation = $this->stringList($decision['explanation'] ?? null);
        $matched = is_array($decision['matched'] ?? null) ? $decision['matched'] : [];

        $allowedRefs = $decisionId !== '' ? [$decisionId] : [];
        foreach ($matched as $m) {
            if (is_array($m) && is_string($m['key'] ?? null) && $m['key'] !== '') {
                $allowedRefs[] = $m['key'];
            }
        }

        $verdict = $allowed ? 'CONSENTITO' : 'NEGATO';
        $fallback = "Accesso {$verdict}".($decisionId !== '' ? " (decision {$decisionId})" : '').'. '
            .($explanation !== [] ? implode(' ', $explanation) : 'Nessun dettaglio aggiuntivo dal PDP.');

        $evidence = [
            'allowed' => $allowed,
            'decision_id' => $decisionId,
            'explanation' => $explanation,
            'matched' => $matched,
        ];

        $userPrompt = ($question !== '' ? $question : 'Spiega questa decisione di accesso.');

        return $this->client->advise('access_explain', self::SYSTEM, $userPrompt, $evidence, $allowedRefs, $fallback);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
