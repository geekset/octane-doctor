<?php

use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Exceptions\InvalidRule;
use Geekset\OctaneDoctor\Rules\Rule;
use Geekset\OctaneDoctor\Scanning\RuleRegistry;
use Geekset\OctaneDoctor\Scanning\ScanContext;

class RegistryFixtureRule implements Rule
{
    public function id(): string
    {
        return 'fixture';
    }

    public function title(): string
    {
        return 'Fixture';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function category(): Category
    {
        return Category::UnknownRisk;
    }

    public function run(ScanContext $context): iterable
    {
        return [];
    }
}

class NotARule {}

it('resolves built-in and custom rules through the container', function () {
    $registry = new RuleRegistry(
        app(),
        [RegistryFixtureRule::class],
        [],
    );

    expect($registry->all())->toHaveCount(1)
        ->and($registry->all()[0])->toBeInstanceOf(RegistryFixtureRule::class);
});

it('rejects classes that do not implement the Rule contract', function () {
    $registry = new RuleRegistry(app(), [NotARule::class], []);

    $registry->all();
})->throws(InvalidRule::class);
