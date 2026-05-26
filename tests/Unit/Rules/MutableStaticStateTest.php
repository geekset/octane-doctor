<?php

use OctaneDoctor\Ast\FileWalker;
use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\RiskClass;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\Builtin\MutableStaticState;
use OctaneDoctor\Scanning\ScanContext;

beforeEach(function () {
    $this->fixturesPath = __DIR__.'/../../Fixtures/StaticState';
});

function runMutableStaticStateRule(string $path): array
{
    $rule = new MutableStaticState(new FileWalker);

    return iterator_to_array(
        $rule->run(new ScanContext(app(), [$path])),
        false,
    );
}

it('flags every class that declares a mutable static property', function () {
    $findings = runMutableStaticStateRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)
        ->toContain('OctaneDoctor\Tests\Fixtures\StaticState\BadCache::$cache')
        ->toContain('OctaneDoctor\Tests\Fixtures\StaticState\BadCounter::$count')
        ->toContain('OctaneDoctor\Tests\Fixtures\StaticState\BadTrait::$tenant');
});

it('ignores classes that only carry instance state and constants', function () {
    $findings = runMutableStaticStateRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)->not->toContain(
        'OctaneDoctor\Tests\Fixtures\StaticState\GoodInstanceState::$cache'
    );
});

it('produces findings with the expected metadata', function () {
    $findings = runMutableStaticStateRule($this->fixturesPath);

    $finding = collect($findings)->firstWhere(
        'symbol',
        'OctaneDoctor\Tests\Fixtures\StaticState\BadCounter::$count'
    );

    expect($finding)->not->toBeNull()
        ->and($finding->ruleId)->toBe('mutable-static-state')
        ->and($finding->severity)->toBe(Severity::Medium)
        ->and($finding->category)->toBe(Category::StaticState)
        ->and($finding->riskClass)->toBe(RiskClass::DataLeak)
        ->and($finding->filePath)->toEndWith('BadCounter.php')
        ->and($finding->line)->toBeGreaterThan(0);
});

it('returns an empty result when no paths exist', function () {
    $findings = runMutableStaticStateRule('/tmp/this-directory-should-not-exist-octane-doctor');

    expect($findings)->toBe([]);
});

it('skips JsonResource::$wrap overrides because they are class-definition config, not request state', function () {
    $findings = runMutableStaticStateRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)->not->toContain(
        'OctaneDoctor\Tests\Fixtures\StaticState\SafeResourceWrap::$wrap'
    );
});

it('skips Eloquent Model::$snakeAttributes and Model::$unguarded overrides', function () {
    $findings = runMutableStaticStateRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeEloquentOverrides::$snakeAttributes')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeEloquentOverrides::$unguarded');
});

it('still flags non-allow-listed static properties on resources that override $wrap', function () {
    $findings = runMutableStaticStateRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)
        ->toContain('OctaneDoctor\Tests\Fixtures\StaticState\UnsafeResourceWithExtraStatic::$cache')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\UnsafeResourceWithExtraStatic::$wrap');
});

it('skips Filament configuration static properties on Filament Resources, Pages and Widgets', function () {
    $findings = runMutableStaticStateRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeFilamentResource::$navigationLabel')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeFilamentResource::$modelLabel')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeFilamentResource::$pluralModelLabel')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeFilamentResource::$navigationIcon')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeFilamentResource::$navigationSort')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeFilamentPage::$view')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeFilamentPage::$navigationIcon')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeFilamentPage::$title')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeFilamentPage::$relationship')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeFilamentWidget::$sort')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\SafeFilamentWidget::$navigationIcon');
});

it('still flags non-Filament static properties on classes that extend Filament parents', function () {
    $findings = runMutableStaticStateRule($this->fixturesPath);

    $symbols = array_map(fn (Finding $f) => $f->symbol, $findings);

    expect($symbols)
        ->toContain('OctaneDoctor\Tests\Fixtures\StaticState\UnsafeFilamentResourceWithExtraStatic::$cache')
        ->not->toContain('OctaneDoctor\Tests\Fixtures\StaticState\UnsafeFilamentResourceWithExtraStatic::$navigationLabel');
});
