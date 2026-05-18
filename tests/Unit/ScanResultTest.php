<?php

use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Scanning\ScanResult;

function makeFinding(Severity $severity): Finding
{
    return new Finding(
        ruleId: 'r',
        title: 't',
        severity: $severity,
        category: Category::UnknownRisk,
        summary: 's',
        whyItMatters: 'w',
        remediation: 'r',
    );
}

it('counts findings by severity', function () {
    $result = new ScanResult(
        findings: [
            makeFinding(Severity::High),
            makeFinding(Severity::High),
            makeFinding(Severity::Low),
        ],
        scannedPaths: [],
        durationMs: 1.0,
    );

    expect($result->countBySeverity())->toBe([
        'high' => 2,
        'medium' => 0,
        'low' => 1,
        'info' => 0,
    ]);
});

it('detects findings at or above the threshold', function () {
    $result = new ScanResult(
        findings: [makeFinding(Severity::Medium)],
        scannedPaths: [],
        durationMs: 0.0,
    );

    expect($result->hasFindingAtOrAbove(Severity::Medium))->toBeTrue()
        ->and($result->hasFindingAtOrAbove(Severity::High))->toBeFalse()
        ->and($result->hasFindingAtOrAbove(Severity::Low))->toBeTrue();
});
