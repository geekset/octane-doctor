<?php

use Illuminate\Support\Facades\Artisan;
use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\RiskClass;
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

    public function riskClass(): RiskClass
    {
        return RiskClass::DataLeak;
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

    $exit = Artisan::call('octane-doctor:rules:view', ['rule' => 'fixture-view-rule']);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('fixture-view-rule')
        ->and($output)->toContain('Fixture view rule')
        ->and($output)->toContain('HIGH')
        ->and($output)->toContain('Category: singleton-safety')
        ->and($output)->toContain('Risk class: data-leak')
        ->and($output)->toContain('It captures stale state.')
        ->and($output)->toContain('Switch to scoped().')
        ->and($output)->toContain('$this->app->scoped(Foo::class);')
        ->and($output)->toContain('https://example.test/docs/fixture-view-rule');
});

it('fails gracefully when the rule id is unknown', function () {
    config()->set('octane-doctor.rules', [RulesViewFixtureRule::class]);
    config()->set('octane-doctor.custom_rules', []);

    $this->artisan('octane-doctor:rules:view', ['rule' => 'does-not-exist'])
        ->expectsOutputToContain("No rule registered with id 'does-not-exist'.")
        ->expectsOutputToContain('Run octane-doctor:rules:list to see every registered rule.')
        ->assertExitCode(1);
});
