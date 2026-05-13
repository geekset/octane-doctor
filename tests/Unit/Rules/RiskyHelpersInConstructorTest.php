<?php

use Geekset\OctaneDoctor\Ast\FileWalker;
use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Builtin\RiskyHelpersInConstructor;
use Geekset\OctaneDoctor\Scanning\ScanContext;

beforeEach(function () {
    $this->fixturesPath = __DIR__.'/../../Fixtures/ConstructorHelpers';
});

function runRiskyHelpersInConstructorRule(string $path): array
{
    $rule = new RiskyHelpersInConstructor(new FileWalker);

    return iterator_to_array(
        $rule->run(new ScanContext(app(), [$path])),
        false,
    );
}

it('flags request() called in constructor', function () {
    $findings = runRiskyHelpersInConstructorRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'CapturesRequest') && str_contains($f->summary, 'request()')
    );

    expect($matches)->not->toBeEmpty();
});

it('flags Auth facade calls in constructor when imported via use statement', function () {
    $findings = runRiskyHelpersInConstructorRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'CapturesAuthFacade')
            && str_contains($f->summary, 'Illuminate\Support\Facades\Auth::id()')
    );

    expect($matches)->not->toBeEmpty();
});

it('flags Auth alias calls in constructor when used via root namespace', function () {
    $findings = runRiskyHelpersInConstructorRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'CapturesAuthAlias')
            && str_contains($f->summary, 'Auth::user()')
    );

    expect($matches)->not->toBeEmpty();
});

it('flags session() called in constructor', function () {
    $findings = runRiskyHelpersInConstructorRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'CapturesSession') && str_contains($f->summary, 'session()')
    );

    expect($matches)->not->toBeEmpty();
});

it('does not flag risky helpers called outside the constructor', function () {
    $findings = runRiskyHelpersInConstructorRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'SafeConstructor')
    );

    expect($matches)->toBeEmpty();
});

it('does not flag unrelated facade static calls inside the constructor', function () {
    $findings = runRiskyHelpersInConstructorRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'SafeWithUnrelatedStaticCall')
    );

    expect($matches)->toBeEmpty();
});

it('does not flag classes using the Dispatchable trait because events are constructed per dispatch', function () {
    $findings = runRiskyHelpersInConstructorRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'SafeEventCapturesAuth')
    );

    expect($matches)->toBeEmpty();
});

it('does not flag FormRequest subclasses because they are constructed per request', function () {
    $findings = runRiskyHelpersInConstructorRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'SafeFormRequestCapturesAuth')
    );

    expect($matches)->toBeEmpty();
});

it('produces findings with the expected metadata', function () {
    $findings = runRiskyHelpersInConstructorRule($this->fixturesPath);

    $finding = collect($findings)->firstWhere(
        fn (Finding $f) => str_contains($f->summary, 'CapturesRequest')
    );

    expect($finding)->not->toBeNull()
        ->and($finding->ruleId)->toBe('risky-helpers-in-constructor')
        ->and($finding->severity)->toBe(Severity::Medium)
        ->and($finding->category)->toBe(Category::RequestState)
        ->and($finding->symbol)->toEndWith('::__construct')
        ->and($finding->line)->toBeGreaterThan(0);
});
