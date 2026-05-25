<?php

use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\RiskClass;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\Builtin\SuspiciousSingletonName;
use OctaneDoctor\Scanning\ScanContext;
use OctaneDoctor\Tests\Fixtures\SuspiciousSingleton\BenignCacheService;
use OctaneDoctor\Tests\Fixtures\SuspiciousSingleton\CurrentUser;
use OctaneDoctor\Tests\Fixtures\SuspiciousSingleton\TenantContext;

function runSuspiciousSingletonNameRule(array $paths = []): array
{
    $rule = new SuspiciousSingletonName;

    return iterator_to_array(
        $rule->run(new ScanContext(app(), $paths)),
        false,
    );
}

it('flags singletons whose class name matches CurrentUser', function () {
    app()->singleton(CurrentUser::class);

    $findings = runSuspiciousSingletonNameRule();

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, CurrentUser::class)
            && str_contains($f->summary, 'CurrentUser')
    );

    expect($matches)->not->toBeEmpty();
});

it('flags singletons whose class name matches TenantContext', function () {
    app()->singleton(TenantContext::class);

    $findings = runSuspiciousSingletonNameRule();

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, TenantContext::class)
    );

    expect($matches)->not->toBeEmpty();
});

it('does not flag singletons with neutral class names', function () {
    app()->singleton(BenignCacheService::class);

    $findings = runSuspiciousSingletonNameRule();

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, BenignCacheService::class)
    );

    expect($matches)->toBeEmpty();
});

it('does not flag non-shared bindings even when the name is suspicious', function () {
    app()->bind(CurrentUser::class);

    $findings = runSuspiciousSingletonNameRule();

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, CurrentUser::class)
    );

    expect($matches)->toBeEmpty();
});

it('skips singletons bound under trusted vendor namespaces', function () {
    $findings = runSuspiciousSingletonNameRule();

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_starts_with($f->symbol ?? '', 'Illuminate\\')
            || str_starts_with($f->symbol ?? '', 'Symfony\\')
    );

    expect($matches)->toBeEmpty();
});

it('skips suspiciously named singletons whose class file is outside the scanned paths', function () {
    app()->singleton(CurrentUser::class);

    $appPath = __DIR__.'/../../../app';
    $findings = runSuspiciousSingletonNameRule([$appPath]);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, CurrentUser::class)
    );

    expect($matches)->toBeEmpty();
});

it('produces findings with the expected metadata', function () {
    app()->singleton(CurrentUser::class);

    $findings = runSuspiciousSingletonNameRule();

    $finding = collect($findings)->firstWhere(
        fn (Finding $f) => str_contains($f->summary, CurrentUser::class)
    );

    expect($finding)->not->toBeNull()
        ->and($finding->ruleId)->toBe('suspicious-singleton-name')
        ->and($finding->severity)->toBe(Severity::Medium)
        ->and($finding->category)->toBe(Category::SingletonSafety)
        ->and($finding->riskClass)->toBe(RiskClass::DataLeak);
});
