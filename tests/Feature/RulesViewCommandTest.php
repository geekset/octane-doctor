<?php

use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Rules\RuleExplanation;
use OctaneDoctor\Scanning\ScanContext;

class RulesViewFixtureRule implements Rule
{
    public function id(): string
    {
        return 'fixture-view-rule';
    }

    public function title(): string
    {
        return 'Fixture view rule';
    }

    public function severity(): Severity
    {
        return Severity::High;
    }

    public function category(): Category
    {
        return Category::SingletonSafety;
    }

    public function explanation(): RuleExplanation
    {
        return new RuleExplanation(
            whyItMatters: 'It captures stale state.',
            remediation: 'Switch to scoped().',
            examples: ['$this->app->scoped(Foo::class);'],
            docsUrl: 'https://example.test/docs/fixture-view-rule',
        );
    }

    public function run(ScanContext $context): iterable
    {
        return [];
    }
}

it('shows the full explanation for the requested rule', function () {
    config()->set('octane-doctor.rules', [RulesViewFixtureRule::class]);
    config()->set('octane-doctor.custom_rules', []);

    $this->artisan('octane-doctor:rules:view', ['rule' => 'fixture-view-rule'])
        ->expectsOutputToContain('Rule: fixture-view-rule')
        ->expectsOutputToContain('Title: Fixture view rule')
        ->expectsOutputToContain('Severity: high')
        ->expectsOutputToContain('Category: singleton-safety')
        ->expectsOutputToContain('It captures stale state.')
        ->expectsOutputToContain('Switch to scoped().')
        ->expectsOutputToContain('$this->app->scoped(Foo::class);')
        ->expectsOutputToContain('Docs: https://example.test/docs/fixture-view-rule')
        ->assertExitCode(0);
});

it('fails gracefully when the rule id is unknown', function () {
    config()->set('octane-doctor.rules', [RulesViewFixtureRule::class]);
    config()->set('octane-doctor.custom_rules', []);

    $this->artisan('octane-doctor:rules:view', ['rule' => 'does-not-exist'])
        ->expectsOutputToContain("No rule registered with id 'does-not-exist'.")
        ->expectsOutputToContain('Run octane-doctor:rules:list to see every registered rule.')
        ->assertExitCode(1);
});
