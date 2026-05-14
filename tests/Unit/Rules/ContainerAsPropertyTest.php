<?php

use Geekset\OctaneDoctor\Ast\FileWalker;
use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Builtin\ContainerAsProperty;
use Geekset\OctaneDoctor\Scanning\ScanContext;

beforeEach(function () {
    $this->fixturesPath = __DIR__.'/../../Fixtures/Container';
});

function runContainerAsPropertyRule(string $path): array
{
    $rule = new ContainerAsProperty(new FileWalker);

    return iterator_to_array(
        $rule->run(new ScanContext(app(), [$path])),
        false,
    );
}

it('flags a constructor-promoted Container property', function () {
    $findings = runContainerAsPropertyRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)->toContain(
        'Geekset\OctaneDoctor\Tests\Fixtures\Container\HoldsContainerProperty::$container'
    );
});

it('flags a regular Application property assigned in the constructor', function () {
    $findings = runContainerAsPropertyRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)->toContain(
        'Geekset\OctaneDoctor\Tests\Fixtures\Container\HoldsApplicationProperty::$app'
    );
});

it('does not flag unrelated typed properties', function () {
    $findings = runContainerAsPropertyRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'SafeService::')
    );

    expect($matches)->toBeEmpty();
});

it('does not flag ServiceProvider subclasses that hold the Application instance', function () {
    $findings = runContainerAsPropertyRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'SafeServiceProviderHoldsApp')
    );

    expect($matches)->toBeEmpty();
});

it('does not flag Dispatchable events that hold the container', function () {
    $findings = runContainerAsPropertyRule($this->fixturesPath);

    $matches = collect($findings)->filter(
        fn (Finding $f) => str_contains($f->summary, 'SafeEventHoldsContainer')
    );

    expect($matches)->toBeEmpty();
});

it('produces findings with the expected metadata', function () {
    $findings = runContainerAsPropertyRule($this->fixturesPath);

    $finding = collect($findings)->firstWhere(
        fn (Finding $f) => str_contains($f->summary, 'HoldsContainerProperty')
    );

    expect($finding)->not->toBeNull()
        ->and($finding->ruleId)->toBe('container-as-property')
        ->and($finding->severity)->toBe(Severity::Medium)
        ->and($finding->category)->toBe(Category::ContainerLifecycle)
        ->and($finding->line)->toBeGreaterThan(0);
});
