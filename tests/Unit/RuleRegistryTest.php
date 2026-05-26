<?php

use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\RiskClass;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Exceptions\InvalidRule;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Rules\RuleExplanation;
use OctaneDoctor\Scanning\RuleRegistry;
use OctaneDoctor\Scanning\ScanContext;

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

    public function riskClass(): RiskClass
    {
        return RiskClass::DataLeak;
    }

    public function explanation(): RuleExplanation
    {
        return new RuleExplanation(whyItMatters: 'fixture', remediation: 'fixture');
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

it('declares a risk class for every built-in rule', function () {
    $builtIn = (array) config('octane-doctor.rules', []);

    $registry = new RuleRegistry(app(), $builtIn, []);

    foreach ($registry->all() as $rule) {
        expect($rule->riskClass())->toBeInstanceOf(RiskClass::class);
    }
});
