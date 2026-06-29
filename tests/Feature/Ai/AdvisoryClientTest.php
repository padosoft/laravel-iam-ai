<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\Iam\Ai\AdvisoryClient;
use Padosoft\Iam\Ai\Contracts\AiProvider;
use Padosoft\Iam\Ai\Governance\HallucinationGuard;
use Padosoft\Iam\Ai\Governance\Redactor;
use Padosoft\Iam\Domain\Audit\Models\AuditEvent;
use Padosoft\Iam\Domain\Audit\Pii\AuditRecorder;

uses(RefreshDatabase::class);

function fakeProvider(callable $complete, string $name = 'fake'): AiProvider
{
    return new class($complete, $name) implements AiProvider
    {
        /** @var callable */
        private $complete;

        public function __construct(callable $complete, private string $providerName)
        {
            $this->complete = $complete;
        }

        public function name(): string
        {
            return $this->providerName;
        }

        public function complete(string $system, string $user): string
        {
            return ($this->complete)($system, $user);
        }
    };
}

function client(AiProvider $provider): AdvisoryClient
{
    return new AdvisoryClient($provider, new Redactor, new HallucinationGuard, app(AuditRecorder::class));
}

it('con AI spenta risponde in modo deterministico e audita', function () {
    config(['iam-ai.enabled' => false]);
    $provider = fakeProvider(fn () => throw new RuntimeException('non deve essere chiamato'));

    $advisory = client($provider)->advise('t', 'sys', 'spiega', ['x' => 1], ['dec_1'], 'RISPOSTA DETERMINISTICA');

    expect($advisory->text)->toBe('RISPOSTA DETERMINISTICA')
        ->and($advisory->aiUsed)->toBeFalse()
        ->and(AuditEvent::query()->where('event_type', 'iam.ai.advisory')->exists())->toBeTrue();
});

it('con AI accesa e output pulito usa la risposta del modello', function () {
    config(['iam-ai.enabled' => true]);
    $provider = fakeProvider(fn () => 'Spiegazione che cita dec_ABC12345.');

    $advisory = client($provider)->advise('t', 'sys', 'spiega', [], ['dec_ABC12345'], 'fallback');

    expect($advisory->aiUsed)->toBeTrue()
        ->and($advisory->guardPassed)->toBeTrue()
        ->and($advisory->text)->toContain('dec_ABC12345');
});

it('un output con ID inventato viene scartato a favore del deterministico', function () {
    config(['iam-ai.enabled' => true]);
    $provider = fakeProvider(fn () => 'In realtà è per via di grn_INVENTATO9999.');

    $advisory = client($provider)->advise('t', 'sys', 'spiega', [], ['dec_OK000001'], 'FALLBACK SICURO');

    expect($advisory->guardPassed)->toBeFalse()
        ->and($advisory->violations)->toContain('grn_INVENTATO9999')
        ->and($advisory->text)->toBe('FALLBACK SICURO');
});

it('il prompt inviato al provider è redatto (nessun segreto)', function () {
    config(['iam-ai.enabled' => true]);
    $seen = null;
    $provider = fakeProvider(function (string $s, string $u) use (&$seen): string {
        $seen = $u;

        return 'ok';
    });

    $advisory = client($provider)->advise('t', 'sys', 'token=Bearer abc.def.ghi', [], [], 'fb');

    expect($seen)->not->toBeNull()
        ->and($seen)->not->toContain('abc.def.ghi')
        ->and($advisory->redacted)->toBeTrue();
});

it('se il transport lancia, si ricade sul deterministico', function () {
    config(['iam-ai.enabled' => true]);
    $provider = fakeProvider(fn () => throw new RuntimeException('rete giù'));

    $advisory = client($provider)->advise('t', 'sys', 'spiega', [], [], 'FALLBACK');

    expect($advisory->text)->toBe('FALLBACK')
        ->and($advisory->aiUsed)->toBeFalse();
});
