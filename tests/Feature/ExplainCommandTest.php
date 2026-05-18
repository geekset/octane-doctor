<?php

use Illuminate\Support\Facades\Artisan;
use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Rules\RuleExplanation;
use OctaneDoctor\Scanning\ScanContext;

class ExplainFixtureRule implements Rule
{
    public function id(): string
    {
        return 'explain-fixture';
    }

    public function title(): string
    {
        return 'Explain fixture rule';
    }

    public function severity(): Severity
    {
        return Severity::High;
    }

    public function category(): Category
    {
        return Category::StaticState;
    }

    public function explanation(): RuleExplanation
    {
        return new RuleExplanation(
            whyItMatters: 'Fixture explanation body.',
            remediation: 'Fixture remediation body.',
            examples: ['$fixture = 1;'],
            docsUrl: 'https://example.test/rules/explain-fixture',
        );
    }

    public function run(ScanContext $context): iterable
    {
        return [];
    }
}

beforeEach(function () {
    config()->set('octane-doctor.rules', [ExplainFixtureRule::class]);
    config()->set('octane-doctor.custom_rules', []);
});

it('prints the full explanation when given a known rule id', function () {
    Artisan::call('octane-doctor:explain', ['rule' => 'explain-fixture']);
    $output = Artisan::output();

    expect($output)
        ->toContain('Rule: explain-fixture')
        ->toContain('Title: Explain fixture rule')
        ->toContain('Severity: high')
        ->toContain('Category: static-state')
        ->toContain('Fixture explanation body.')
        ->toContain('Fixture remediation body.')
        ->toContain('$fixture = 1;')
        ->toContain('https://example.test/rules/explain-fixture');
});

it('exits non-zero and lists known ids when the requested rule is missing', function () {
    $exit = Artisan::call('octane-doctor:explain', ['rule' => 'no-such-rule']);
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)
        ->toContain("No rule registered with id 'no-such-rule'.")
        ->toContain('explain-fixture');
});

it('lists every registered rule when called without an argument', function () {
    $exit = Artisan::call('octane-doctor:explain');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)
        ->toContain('Registered rules:')
        ->toContain('explain-fixture')
        ->toContain('Explain fixture rule')
        ->toContain('high severity / static-state');
});
