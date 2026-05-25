<?php

use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\RiskClass;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;

it('exposes its data through toArray', function () {
    $finding = new Finding(
        ruleId: 'static-state',
        title: 'Mutable static state',
        severity: Severity::High,
        category: Category::StaticState,
        riskClass: RiskClass::DataLeak,
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
            'risk_class' => 'data-leak',
            'file_path' => '/app/Foo.php',
            'line' => 12,
            'symbol' => 'Foo::$cache',
            'docs_url' => null,
        ])
        ->and($finding->toArray()['fingerprint'])->toBeString()->toHaveLength(16)
        ->and($finding->toArray())->not->toHaveKey('autofixable')
        ->and($finding->toArray())->not->toHaveKey('autofix_strategy_id');
});

it('relativizes the file path when the base path is a prefix', function () {
    $finding = new Finding(
        ruleId: 'static-state',
        title: 'Mutable static state',
        severity: Severity::High,
        category: Category::StaticState,
        riskClass: RiskClass::DataLeak,
        summary: 'summary',
        whyItMatters: 'why',
        remediation: 'fix',
        filePath: '/Users/runner/work/project/app/Foo.php',
    );

    $relative = $finding->relativizeFilePath('/Users/runner/work/project');

    expect($relative->filePath)->toBe('app/Foo.php');
});

it('leaves the file path untouched when outside the base path', function () {
    $finding = new Finding(
        ruleId: 'static-state',
        title: 'Mutable static state',
        severity: Severity::High,
        category: Category::StaticState,
        riskClass: RiskClass::DataLeak,
        summary: 'summary',
        whyItMatters: 'why',
        remediation: 'fix',
        filePath: '/somewhere/else/Foo.php',
    );

    $relative = $finding->relativizeFilePath('/Users/runner/work/project');

    expect($relative->filePath)->toBe('/somewhere/else/Foo.php');
});

it('produces the same fingerprint across machines once paths are relativized', function () {
    $build = fn (string $base) => (new Finding(
        ruleId: 'static-state',
        title: 'Mutable static state',
        severity: Severity::High,
        category: Category::StaticState,
        riskClass: RiskClass::DataLeak,
        summary: 'Class App\\Foo declares mutable static property $cache.',
        whyItMatters: 'why',
        remediation: 'fix',
        filePath: $base.'/app/Foo.php',
        line: 12,
        symbol: 'App\\Foo::$cache',
    ))->relativizeFilePath($base);

    expect($build('/Users/gayan/dev/project')->fingerprint())
        ->toBe($build('/home/runner/work/project')->fingerprint());
});

it('produces a deterministic fingerprint for the same input', function () {
    $args = [
        'ruleId' => 'rule-a',
        'title' => 'A',
        'severity' => Severity::Medium,
        'category' => Category::SingletonSafety,
        'riskClass' => RiskClass::DataLeak,
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
        'riskClass' => RiskClass::DataLeak,
        'whyItMatters' => 'because',
        'remediation' => 'do this',
        'filePath' => '/app/X.php',
        'symbol' => 'X',
    ];

    $one = new Finding(...[...$base, 'summary' => 'first']);
    $two = new Finding(...[...$base, 'summary' => 'second']);

    expect($one->fingerprint())->not->toBe($two->fingerprint());
});
