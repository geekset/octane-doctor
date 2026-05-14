<?php

use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Rule;
use Geekset\OctaneDoctor\Rules\RuleExplanation;
use Geekset\OctaneDoctor\Scanning\ScanContext;
use Illuminate\Support\Facades\Artisan;

class BaselineFixtureRule implements Rule
{
    public function id(): string
    {
        return 'baseline-fixture-rule';
    }

    public function title(): string
    {
        return 'Baseline fixture';
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
        return new RuleExplanation(whyItMatters: 'fixture', remediation: 'fixture');
    }

    public function run(ScanContext $context): iterable
    {
        yield new Finding(
            ruleId: 'baseline-fixture-rule',
            title: 'Baseline finding',
            severity: Severity::High,
            category: Category::StaticState,
            summary: 'first finding',
            whyItMatters: 'why',
            remediation: 'fix',
            filePath: '/app/First.php',
            line: 10,
        );
    }
}

class BaselineFixtureRuleWithExtra implements Rule
{
    public function id(): string
    {
        return 'baseline-fixture-rule';
    }

    public function title(): string
    {
        return 'Baseline fixture';
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
        return new RuleExplanation(whyItMatters: 'fixture', remediation: 'fixture');
    }

    public function run(ScanContext $context): iterable
    {
        yield new Finding(
            ruleId: 'baseline-fixture-rule',
            title: 'Baseline finding',
            severity: Severity::High,
            category: Category::StaticState,
            summary: 'first finding',
            whyItMatters: 'why',
            remediation: 'fix',
            filePath: '/app/First.php',
            line: 10,
        );

        yield new Finding(
            ruleId: 'baseline-fixture-rule',
            title: 'Baseline finding',
            severity: Severity::High,
            category: Category::StaticState,
            summary: 'second finding',
            whyItMatters: 'why',
            remediation: 'fix',
            filePath: '/app/Second.php',
            line: 20,
        );
    }
}

beforeEach(function () {
    $this->baselinePath = sys_get_temp_dir().'/octane-doctor-baseline-feature-'.uniqid().'.json';

    config()->set('octane-doctor.baseline', $this->baselinePath);
    config()->set('octane-doctor.paths', []);
});

afterEach(function () {
    if (is_file($this->baselinePath)) {
        unlink($this->baselinePath);
    }
});

it('writes a baseline file containing the current findings', function () {
    config()->set('octane-doctor.rules', [BaselineFixtureRule::class]);

    $exit = Artisan::call('octane-doctor:baseline');

    expect($exit)->toBe(0)
        ->and(is_file($this->baselinePath))->toBeTrue();

    $payload = json_decode(file_get_contents($this->baselinePath), true);

    expect($payload['fingerprint_count'])->toBe(1);
});

it('treats baselined findings as suppressed on the next scan', function () {
    config()->set('octane-doctor.rules', [BaselineFixtureRule::class]);
    config()->set('octane-doctor.fail_on', 'high');

    Artisan::call('octane-doctor:baseline');

    $exit = Artisan::call('octane-doctor:scan');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('No Octane readiness findings detected.')
        ->and($output)->toContain('1 finding suppressed by baseline');
});

it('still fails on new findings introduced after the baseline was taken', function () {
    config()->set('octane-doctor.rules', [BaselineFixtureRule::class]);
    config()->set('octane-doctor.fail_on', 'high');

    Artisan::call('octane-doctor:baseline');

    config()->set('octane-doctor.rules', [BaselineFixtureRuleWithExtra::class]);

    $exit = Artisan::call('octane-doctor:scan');
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('second finding');
});

it('--no-baseline ignores the baseline file', function () {
    config()->set('octane-doctor.rules', [BaselineFixtureRule::class]);
    config()->set('octane-doctor.fail_on', 'high');

    Artisan::call('octane-doctor:baseline');

    $exit = Artisan::call('octane-doctor:scan', ['--no-baseline' => true]);

    expect($exit)->toBe(1);
});
