<?php

declare(strict_types=1);

namespace Padosoft\Iam\Ai\Governance;

/**
 * Pipeline di redaction PRE-prompt (doc 15 §3.1). Niente segreti né PII non necessaria deve mai
 * raggiungere il modello: token Bearer, client secret, private key, password, OTP/recovery code,
 * cookie/session id, email, IP. È deterministica e obbligatoria prima di OGNI chiamata AI.
 * Fail-safe: in caso di dubbio si redige (meglio un prompt meno ricco che un leak).
 */
final class Redactor
{
    /** @var array<string, string> pattern => placeholder */
    private const PATTERNS = [
        // Bearer/Basic/JWT e token opachi lunghi
        '/\b(?:Bearer|Basic)\s+[A-Za-z0-9._\-\/+=]+/i' => '[REDACTED_AUTH]',
        '/\beyJ[A-Za-z0-9._\-]{10,}/' => '[REDACTED_JWT]',
        // Private key PEM
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s' => '[REDACTED_PRIVATE_KEY]',
        // password/secret/token/cookie espliciti in chiave: valore (fino a fine riga, così un valore
        // con spazi non lascia trapelare la coda del segreto).
        '/\b(password|passwd|secret|client_secret|api[_-]?key|token|otp|recovery[_-]?code|cookie|set-cookie|session[_-]?id)\b\s*[:=]\s*[^\r\n]+/i' => '$1=[REDACTED]',
        // Email (PII)
        '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/' => '[REDACTED_EMAIL]',
        // IPv4
        '/\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\b/' => '[REDACTED_IP]',
        // Stringhe esadecimali lunghe (chiavi/hash grezzi)
        '/\b[A-Fa-f0-9]{32,}\b/' => '[REDACTED_HEX]',
        // Blob base64 lunghi (API key/token opachi). 40+ char contigui del charset base64 non
        // compaiono nel linguaggio naturale: trattati come segreto. Va DOPO l'hex per non spezzarlo.
        '/[A-Za-z0-9+\/]{40,}={0,2}/' => '[REDACTED_B64]',
    ];

    /** Ritorna true se il testo conteneva qualcosa che è stato redatto. */
    public bool $didRedact = false;

    /** @phpstan-impure muta $didRedact come side-effect osservabile */
    public function redact(string $text): string
    {
        $out = $text;
        foreach (self::PATTERNS as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $out);
            if (is_string($result) && $result !== $out) {
                $this->didRedact = true;
                $out = $result;
            }
        }

        return $out;
    }

    /**
     * Redige ricorsivamente i valori stringa di una struttura (evidenze) preservando le chiavi.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     *
     * @phpstan-impure delega a redact() che muta $didRedact
     */
    public function redactArray(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $out[$key] = $this->redact($value);
            } elseif (is_array($value)) {
                $out[$key] = $this->redactArray($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
