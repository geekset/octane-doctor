<?php

use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;

it('exposes its data through toArray', function () {
    $finding = new Finding(
        ruleId: 'static-state',
        title: 'Mutable static state',
        severity: Severity::High,
        category: Category::StaticState,
        summary: 'Class Foo has a mutable static property.',
        whyItMatters: 'Static state persists across requests under Octane.',
        remediation: 'Use instance state or a request-scoped binding.',
        filePath: '/app/Foo.php',
        line: 12,
        symbol: 'Foo::$cache',
    );

    expect($finding->toArray())
        ->toMatchArray([
            'rule_id' => 'static-state',
            'severity' => 'high',
            'category' => 'static-state',
            'file_path' => '/app/Foo.php',
            'line' => 12,
            'symbol' => 'Foo::$cache',
            'autofixable' => false,
            'autofix_strategy_id' => null,
            'docs_url' => null,
        ])
        ->and($finding->toArray()['fingerprint'])->toBeString()->toHaveLength(16);
});

it('produces a deterministic fingerprint for the same input', function () {
    $args = [
        'ruleId' => 'rule-a',
        'title' => 'A',
        'severity' => Severity::Medium,
        'category' => Category::SingletonSafety,
        'summary' => 'something',
        'whyItMatters' => 'because',
        'remediation' => 'do this',
        'filePath' => '/app/X.php',
        'symbol' => 'X',
    ];

    expect((new Finding(...$args))->fingerprint())
        ->toBe((new Finding(...$args))->fingerprint());
});

it('produces different fingerprints when the summary changes', function () {
    $base = [
        'ruleId' => 'rule-a',
        'title' => 'A',
        'severity' => Severity::Medium,
        'category' => Category::SingletonSafety,
        'whyItMatters' => 'because',
        'remediation' => 'do this',
        'filePath' => '/app/X.php',
        'symbol' => 'X',
    ];

    $one = new Finding(...[...$base, 'summary' => 'first']);
    $two = new Finding(...[...$base, 'summary' => 'second']);

    expect($one->fingerprint())->not->toBe($two->fingerprint());
});
