<?php

declare(strict_types=1);

namespace Padosoft\Iam\Ai\Governance;

/**
 * Hallucination-guard (doc 15 §3.2): l'output dell'AI può citare SOLO identificatori presenti nelle
 * evidenze fornite dai tool deterministici. Qualsiasi ID "inventato" (un decision_id/grant/evento
 * mai passato come evidenza) è una violazione → l'advisory viene marcato come non affidabile. È il
 * meccanismo che impedisce all'AI di "dire sì/no" o citare prove che non esistono.
 */
final class HallucinationGuard
{
    // ID interni: prefisso tipo `dec_`/`grn_`/`evt_`/`decision-` + base32/alnum, oppure ULID nudo
    // (26 char), oppure UUID standard — un modello potrebbe inventare un riferimento in uno qualunque
    // di questi formati: tutti vanno verificati contro le evidenze, altrimenti il guard è aggirabile.
    // Il prefisso accetta fino a 12 char e sia `_` sia `-` come separatore (es. `campaign_`,
    // `decision-…`) per non lasciare scoperti formati di ID più lunghi.
    private const PREFIXED_REF = '/\b[a-z][a-z0-9]{1,11}[_-][0-9A-Za-z]{8,}\b/';

    private const BARE_ULID = '/\b[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}\b/';

    private const UUID = '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i';

    /**
     * @param  list<string>  $allowedRefs  riferimenti reali ammessi (dalle evidenze)
     * @return list<string> riferimenti citati nell'output ma NON presenti tra quelli ammessi
     */
    public function violations(string $output, array $allowedRefs): array
    {
        $allowed = array_flip($allowedRefs);
        $found = [];

        foreach ([self::PREFIXED_REF, self::BARE_ULID, self::UUID] as $pattern) {
            if (preg_match_all($pattern, $output, $matches)) {
                foreach ($matches[0] as $ref) {
                    if (!isset($allowed[$ref])) {
                        $found[$ref] = true;
                    }
                }
            }
        }

        return array_keys($found);
    }

    /**
     * @param  list<string>  $allowedRefs
     */
    public function passes(string $output, array $allowedRefs): bool
    {
        return $this->violations($output, $allowedRefs) === [];
    }
}
