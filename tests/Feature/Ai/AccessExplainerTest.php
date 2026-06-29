<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\Iam\Ai\Modules\AccessExplainer;
use Padosoft\Iam\Domain\Audit\Models\AuditEvent;

uses(RefreshDatabase::class);

it('spiega una decisione del PDP citando il decision_id (AI spenta)', function () {
    config(['iam-ai.enabled' => false]);

    $decision = [
        'allowed' => true,
        'decision_id' => 'dec_01ABCDEF',
        'explanation' => ['User ha il ruolo warehouse:stock_operator', 'Il ruolo concede warehouse:stock.read'],
        'matched' => [['type' => 'role', 'key' => 'warehouse:stock_operator']],
    ];

    $advisory = app(AccessExplainer::class)->explain($decision, 'Perché può leggere lo stock?');

    expect($advisory->text)->toContain('CONSENTITO')
        ->and($advisory->text)->toContain('warehouse:stock_operator')
        ->and($advisory->citations)->toContain('dec_01ABCDEF')
        ->and($advisory->aiUsed)->toBeFalse()
        ->and(AuditEvent::query()->where('event_type', 'iam.ai.advisory')->exists())->toBeTrue();
});

it('spiega una decisione di deny', function () {
    config(['iam-ai.enabled' => false]);

    $advisory = app(AccessExplainer::class)->explain([
        'allowed' => false,
        'decision_id' => 'dec_DENY0001',
        'explanation' => ['Nessun permit valido → default-deny (fail-closed).'],
        'matched' => [],
    ]);

    expect($advisory->text)->toContain('NEGATO')
        ->and($advisory->text)->toContain('default-deny');
});
