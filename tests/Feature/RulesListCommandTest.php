<?php

use Illuminate\Support\Facades\Artisan;
use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\RiskClass;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Rules\RuleExplanation;
use OctaneDoctor\Scanning\ScanContext;

class RulesListFixtureRule implements Rule
{
    public function id(): string
    {
        return 'fixture-list-rule';
    }

    public function title(): string
    {
        return 'Fixture list rule';
    }

    public function severity(): Severity
    {
        return Severity::Medium;
    }

    public function category(): Category
    {
        return Category::StaticState;
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

it('lists every registered rule in the table output', function () {
    config()->set('octane-doctor.rules', [RulesListFixtureRule::class]);
    config()->set('octane-doctor.custom_rules', []);

    $exit = Artisan::call('octane-doctor:rules:list');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('fixture-list-rule')
        ->and($output)->toContain('Fixture list rule')
        ->and($output)->toContain('MEDIUM')
        ->and($output)->toContain('static-state')
        ->and($output)->toContain('data-leak');
});

it('emits the rule list as JSON when --format=json is set', function () {
    config()->set('octane-doctor.rules', [RulesListFixtureRule::class]);
    config()->set('octane-doctor.custom_rules', []);

    Artisan::call('octane-doctor:rules:list', ['--format' => 'json']);
    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)
        ->toHaveKey('schema_version', '1')
        ->and($payload['rules'])->toHaveCount(1)
        ->and($payload['rules'][0])
        ->toHaveKey('id', 'fixture-list-rule')
        ->toHaveKey('severity', 'medium')
        ->toHaveKey('category', 'static-state')
        ->toHaveKey('risk_class', 'data-leak')
        ->toHaveKey('title', 'Fixture list rule');
});

it('reports when no rules are registered', function () {
    config()->set('octane-doctor.rules', []);
    config()->set('octane-doctor.custom_rules', []);

    $this->artisan('octane-doctor:rules:list')
        ->expectsOutputToContain('No rules are currently registered.')
        ->assertExitCode(0);
});
