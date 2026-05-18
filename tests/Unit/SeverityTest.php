<?php

use OctaneDoctor\Enums\Severity;

it('orders severities so high outranks lower levels', function () {
    expect(Severity::High->isAtLeast(Severity::Medium))->toBeTrue()
        ->and(Severity::Medium->isAtLeast(Severity::High))->toBeFalse()
        ->and(Severity::Info->isAtLeast(Severity::Info))->toBeTrue()
        ->and(Severity::Low->isAtLeast(Severity::Info))->toBeTrue();
});
