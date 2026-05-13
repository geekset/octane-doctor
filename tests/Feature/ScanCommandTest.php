<?php

use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Rule;
use Geekset\OctaneDoctor\Scanning\ScanContext;
use Illuminate\Support\Facades\Artisan;

class CommandFixtureRule implements Rule
{
    public static Severity $severity = Severity::High;

    public function id(): string
    {
        return 'fixture-rule';
    }

    public function title(): string
    {
        return 'Fixture rule';
    }

    public function severity(): Severity
    {
        return static::$severity;
    }

    public function category(): Category
    {
        return Category::StaticState;
    }

    public function run(ScanContext $context): iterable
    {
        yield new Finding(
            ruleId: 'fixture-rule',
            title: 'Fixture finding',
            severity: static::$severity,
            category: Category::StaticState,
            summary: 'Detected a fixture risk.',
            whyItMatters: 'It explains the danger.',
            remediation: 'It explains the fix.',
            filePath: '/app/Foo.php',
            line: 42,
        );
    }
}

class EmptyFixtureRule implements Rule
{
    public function id(): string
    {
        return 'empty-rule';
    }

    public function title(): string
    {
        return 'Empty rule';
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

it('exits 0 and reports no findings when none are produced', function () {
    config()->set('octane-doctor.rules', [EmptyFixtureRule::class]);
    config()->set('octane-doctor.paths', []);

    $this->artisan('octane-doctor:scan')
        ->expectsOutputToContain('No Octane readiness findings detected.')
        ->assertExitCode(0);
});

it('renders findings and exits non-zero when the threshold is hit', function () {
    CommandFixtureRule::$severity = Severity::High;
    config()->set('octane-doctor.rules', [CommandFixtureRule::class]);
    config()->set('octane-doctor.paths', []);
    config()->set('octane-doctor.fail_on', 'high');

    $this->artisan('octane-doctor:scan')
        ->expectsOutputToContain('Fixture finding')
        ->expectsOutputToContain('at /app/Foo.php:42')
        ->assertExitCode(1);
});

it('exits 0 when a finding sits below the configured threshold', function () {
    CommandFixtureRule::$severity = Severity::Low;
    config()->set('octane-doctor.rules', [CommandFixtureRule::class]);
    config()->set('octane-doctor.paths', []);
    config()->set('octane-doctor.fail_on', 'high');

    $this->artisan('octane-doctor:scan')->assertExitCode(0);
});

it('respects the --fail-on override', function () {
    CommandFixtureRule::$severity = Severity::Low;
    config()->set('octane-doctor.rules', [CommandFixtureRule::class]);
    config()->set('octane-doctor.paths', []);
    config()->set('octane-doctor.fail_on', 'high');

    $this->artisan('octane-doctor:scan', ['--fail-on' => 'low'])->assertExitCode(1);
});

it('emits a stable JSON document when --format=json is set', function () {
    CommandFixtureRule::$severity = Severity::High;
    config()->set('octane-doctor.rules', [CommandFixtureRule::class]);
    config()->set('octane-doctor.paths', []);
    config()->set('octane-doctor.fail_on', 'never');

    $exit = Artisan::call('octane-doctor:scan', ['--format' => 'json']);
    $output = trim(Artisan::output());

    $payload = json_decode($output, true);

    expect($exit)->toBe(0)
        ->and($payload)
        ->toHaveKey('schema_version', '1')
        ->and($payload['summary'])
        ->toHaveKey('total', 1)
        ->toHaveKey('by_severity')
        ->toHaveKey('by_category')
        ->toHaveKey('scanned_paths')
        ->toHaveKey('duration_ms')
        ->and($payload['summary']['by_severity']['high'])->toBe(1)
        ->and($payload['findings'])->toHaveCount(1)
        ->and($payload['findings'][0])
        ->toHaveKey('rule_id', 'fixture-rule')
        ->toHaveKey('severity', 'high')
        ->toHaveKey('fingerprint');
});

it('emits empty findings array in JSON when nothing is detected', function () {
    config()->set('octane-doctor.rules', []);
    config()->set('octane-doctor.paths', []);

    Artisan::call('octane-doctor:scan', ['--format' => 'json']);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['summary']['total'])->toBe(0)
        ->and($payload['findings'])->toBe([]);
});

it('falls back to table output when --format is invalid', function () {
    config()->set('octane-doctor.rules', []);
    config()->set('octane-doctor.paths', []);

    $this->artisan('octane-doctor:scan', ['--format' => 'xml'])
        ->expectsOutputToContain('No Octane readiness findings detected.')
        ->assertExitCode(0);
});
