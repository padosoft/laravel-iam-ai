<?php

declare(strict_types=1);

namespace Padosoft\Iam\Ai;

/**
 * Esito di un'interrogazione AI advisory (doc 15 §1). Porta il testo, le citazioni alle evidenze
 * reali e i flag di governance: se l'AI è stata usata (o si è risposto in modo deterministico), se
 * il prompt è stato redatto, e se l'hallucination-guard ha approvato l'output. È SEMPRE una proposta:
 * a decidere è il PDP, mai questo.
 */
final readonly class Advisory
{
    /**
     * @param  list<string>  $citations  riferimenti a evidenze reali citate
     * @param  list<string>  $violations  ID inventati/non supportati rilevati dal guard
     */
    public function __construct(
        public string $text,
        public array $citations = [],
        public bool $aiUsed = false,
        public bool $redacted = false,
        public bool $guardPassed = true,
        public array $violations = [],
        public string $provider = 'deterministic',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'citations' => $this->citations,
            'ai_used' => $this->aiUsed,
            'redacted' => $this->redacted,
            'guard_passed' => $this->guardPassed,
            'violations' => $this->violations,
            'provider' => $this->provider,
            'advisory_only' => true,
        ];
    }
}
