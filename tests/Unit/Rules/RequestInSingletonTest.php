<?php

use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\RiskClass;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\Builtin\RequestInSingleton;
use OctaneDoctor\Scanning\ScanContext;
use OctaneDoctor\Tests\Fixtures\RequestInSingleton\AcceptsAuthManager;
use OctaneDoctor\Tests\Fixtures\RequestInSingleton\AcceptsCache;
use OctaneDoctor\Tests\Fixtures\RequestInSingleton\AcceptsGuard;
use OctaneDoctor\Tests\Fixtures\RequestInSingleton\AcceptsRequest;

function runRequestInSingletonRule(array $paths = []): array
{
    $rule = new RequestInSingleton;

    return iterator_to_array(
        $rule->run(new ScanContext(app(), $paths)),
        false,
    );
}

it('flags a singleton whose constructor accepts a Request', function () {
    app()->singleton(AcceptsRequest::class);

    $findings = runRequestInSingletonRule();

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, AcceptsRequest::class)
            && str_contains($f->summary, 'Illuminate\Http\Request')
    );

    expect($matches)->not->toBeEmpty();
});

it('flags a singleton whose constructor accepts a Guard', function () {
    app()->singleton(AcceptsGuard::class);

    $findings = runRequestInSingletonRule();

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, AcceptsGuard::class)
            && str_contains($f->summary, 'Illuminate\Contracts\Auth\Guard')
    );

    expect($matches)->not->toBeEmpty();
});

it('does not flag singletons that accept AuthManager (Octane flushes auth state)', function () {
    app()->singleton(AcceptsAuthManager::class);

    $findings = runRequestInSingletonRule();

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, AcceptsAuthManager::class)
    );

    expect($matches)->toBeEmpty();
});

it('does not flag singletons whose constructor accepts unrelated types', function () {
    app()->singleton(AcceptsCache::class);

    $findings = runRequestInSingletonRule();

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, AcceptsCache::class)
    );

    expect($matches)->toBeEmpty();
});

it('does not flag bindings that are not shared', function () {
    app()->bind(AcceptsRequest::class);

    $findings = runRequestInSingletonRule();

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, AcceptsRequest::class)
    );

    expect($matches)->toBeEmpty();
});

it('skips singletons bound under trusted vendor namespaces', function () {
    $findings = runRequestInSingletonRule();

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_starts_with($f->summary, 'Singleton Illuminate\\')
            || str_starts_with($f->summary, 'Singleton Symfony\\')
    );

    expect($matches)->toBeEmpty();
});

it('skips singletons whose concrete file is outside the scanned paths', function () {
    app()->singleton(AcceptsRequest::class);

    $appPath = __DIR__.'/../../../app';
    $findings = runRequestInSingletonRule([$appPath]);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, AcceptsRequest::class)
    );

    expect($matches)->toBeEmpty();
});

it('includes singletons whose concrete file sits inside the scanned paths', function () {
    app()->singleton(AcceptsRequest::class);

    $fixturesPath = dirname((new ReflectionClass(AcceptsRequest::class))->getFileName());
    $findings = runRequestInSingletonRule([$fixturesPath]);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, AcceptsRequest::class)
    );

    expect($matches)->not->toBeEmpty();
});

it('produces findings with the expected metadata', function () {
    app()->singleton(AcceptsRequest::class);

    $findings = runRequestInSingletonRule();

    $finding = collect($findings)->firstWhere(
        fn (Finding $f) => str_contains($f->summary, AcceptsRequest::class)
    );

    expect($finding)->not->toBeNull()
        ->and($finding->ruleId)->toBe('request-in-singleton')
        ->and($finding->severity)->toBe(Severity::High)
        ->and($finding->category)->toBe(Category::SingletonSafety)
        ->and($finding->riskClass)->toBe(RiskClass::DataLeak)
        ->and($finding->symbol)->toEndWith('::__construct');
});
