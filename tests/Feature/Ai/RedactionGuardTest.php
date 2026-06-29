<?php

declare(strict_types=1);

use Padosoft\Iam\Ai\Governance\HallucinationGuard;
use Padosoft\Iam\Ai\Governance\Redactor;

it('redige segreti e PII prima del prompt', function () {
    $r = new Redactor;

    $out = $r->redact('Authorization: Bearer abc.def.ghi e password=Sup3rSecret per mario@acme.it da 10.0.0.5');

    expect($out)->not->toContain('Bearer abc')
        ->and($out)->not->toContain('Sup3rSecret')
        ->and($out)->not->toContain('mario@acme.it')
        ->and($out)->not->toContain('10.0.0.5')
        ->and($r->didRedact)->toBeTrue();
});

it('redige una private key PEM e gli hash lunghi', function () {
    $r = new Redactor;
    $pem = "-----BEGIN PRIVATE KEY-----\nMIIBVAIBADANBg\n-----END PRIVATE KEY-----";

    $out = $r->redact($pem.' hash '.str_repeat('a', 40));

    expect($out)->toContain('[REDACTED_PRIVATE_KEY]')
        ->and($out)->toContain('[REDACTED_HEX]');
});

it('non altera testo innocuo e segnala nessuna redazione', function () {
    $r = new Redactor;

    $out = $r->redact('Mario ha il ruolo warehouse:stock_operator.');

    expect($out)->toBe('Mario ha il ruolo warehouse:stock_operator.')
        ->and($r->didRedact)->toBeFalse();
});

it('il guard rileva ID inventati non presenti nelle evidenze', function () {
    $g = new HallucinationGuard;

    $violations = $g->violations('Vedi dec_REALE01 ma anche grn_INVENTATO99', ['dec_REALE01']);

    expect($violations)->toContain('grn_INVENTATO99')
        ->and($violations)->not->toContain('dec_REALE01');
});

it('il guard passa quando tutti i riferimenti sono ammessi', function () {
    $g = new HallucinationGuard;

    expect($g->passes('Concesso da dec_ABC12345 via grn_XYZ98765', ['dec_ABC12345', 'grn_XYZ98765']))->toBeTrue();
});

it('il guard intercetta anche un UUID inventato (non solo i nostri ID)', function () {
    $g = new HallucinationGuard;

    $violations = $g->violations('Per via dell\'evento 550e8400-e29b-41d4-a716-446655440000.', []);

    expect($violations)->toContain('550e8400-e29b-41d4-a716-446655440000');
});

it('redige un segreto con spazi fino a fine riga', function () {
    $r = new Redactor;

    $out = $r->redact("secret = una pass phrase con spazi\nriga ok");

    expect($out)->not->toContain('una pass phrase con spazi')
        ->and($out)->toContain('riga ok');
});

it('redige credenziali Basic e blob base64 opachi', function () {
    $r = new Redactor;
    $b64 = 'QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVowMTIzNDU2Nzg5'; // 48 char base64

    $out = $r->redact("Authorization: Basic dXNlcjpwYXNz e chiave {$b64}");

    expect($out)->not->toContain('dXNlcjpwYXNz')
        ->and($out)->not->toContain($b64)
        ->and($r->didRedact)->toBeTrue();
});

it('il guard intercetta ID inventati con prefisso lungo o separatore trattino', function () {
    $g = new HallucinationGuard;

    $violations = $g->violations('Vedi campaign_01ARYZ6S41 e decision-99887766AB', []);

    expect($violations)->toContain('campaign_01ARYZ6S41')
        ->and($violations)->toContain('decision-99887766AB');
});
