<?php

use OctaneDoctor\Ast\FileWalker;
use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\Builtin\RequestContextAsProperty;
use OctaneDoctor\Scanning\ScanContext;

beforeEach(function () {
    $this->fixturesPath = __DIR__.'/../../Fixtures/RequestContext';
});

function runRequestContextAsPropertyRule(string $path): array
{
    $rule = new RequestContextAsProperty(new FileWalker);

    return iterator_to_array(
        $rule->run(new ScanContext(app(), [$path])),
        false,
    );
}

it('flags a typed property holding a Request instance', function () {
    $findings = runRequestContextAsPropertyRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)->toContain(
        'OctaneDoctor\Tests\Fixtures\RequestContext\HoldsRequestProperty::$request'
    );
});

it('flags a constructor-promoted Request property', function () {
    $findings = runRequestContextAsPropertyRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)->toContain(
        'OctaneDoctor\Tests\Fixtures\RequestContext\HoldsPromotedRequest::$request'
    );
});

it('flags an auth Guard property', function () {
    $findings = runRequestContextAsPropertyRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)->toContain(
        'OctaneDoctor\Tests\Fixtures\RequestContext\HoldsAuthGuard::$guard'
    );
});

it('does not flag unrelated typed properties', function () {
    $findings = runRequestContextAsPropertyRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'SafeService')
    );

    expect($matches)->toBeEmpty();
});

it('does not flag FormRequest subclasses', function () {
    $findings = runRequestContextAsPropertyRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'SafeFormRequestHoldsRequest')
    );

    expect($matches)->toBeEmpty();
});

it('does not flag Dispatchable events', function () {
    $findings = runRequestContextAsPropertyRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'SafeEventHoldsRequest')
    );

    expect($matches)->toBeEmpty();
});

it('does not flag Controller subclasses', function () {
    $findings = runRequestContextAsPropertyRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'SafeControllerHoldsRequest')
    );

    expect($matches)->toBeEmpty();
});

it('produces findings with the expected metadata', function () {
    $findings = runRequestContextAsPropertyRule($this->fixturesPath);

    $finding = collect($findings)->firstWhere(
        fn (Finding $f) => str_contains($f->summary, 'HoldsRequestProperty')
    );

    expect($finding)->not->toBeNull()
        ->and($finding->ruleId)->toBe('request-context-as-property')
        ->and($finding->severity)->toBe(Severity::High)
        ->and($finding->category)->toBe(Category::RequestState)
        ->and($finding->line)->toBeGreaterThan(0);
});
