<?php

use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Builtin\RequestInSingleton;
use Geekset\OctaneDoctor\Scanning\ScanContext;
use Geekset\OctaneDoctor\Tests\Fixtures\RequestInSingleton\AcceptsCache;
use Geekset\OctaneDoctor\Tests\Fixtures\RequestInSingleton\AcceptsGuard;
use Geekset\OctaneDoctor\Tests\Fixtures\RequestInSingleton\AcceptsRequest;

function runRequestInSingletonRule(): array
{
    $rule = new RequestInSingleton;

    return iterator_to_array(
        $rule->run(new ScanContext(app(), [])),
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
        ->and($finding->symbol)->toEndWith('::__construct');
});
