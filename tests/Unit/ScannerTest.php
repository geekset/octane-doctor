<?php

use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Rule;
use Geekset\OctaneDoctor\Rules\RuleExplanation;
use Geekset\OctaneDoctor\Scanning\ScanContext;
use Geekset\OctaneDoctor\Scanning\Scanner;

function octaneDoctorFinding(string $ruleId, Severity $severity, ?int $line = null): Finding
{
    return new Finding(
        ruleId: $ruleId,
        title: 'fixture',
        severity: $severity,
        category: Category::UnknownRisk,
        summary: 'summary',
        whyItMatters: 'why',
        remediation: 'fix',
        filePath: '/app/Foo.php',
        line: $line,
    );
}

class FixtureRule implements Rule
{
    /**
     * @param  array<int, Finding>  $findings
     */
    public function __construct(
        public string $ruleId,
        public array $findings,
    ) {}

    public function id(): string
    {
        return $this->ruleId;
    }

    public function title(): string
    {
        return 'Fixture rule';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function category(): Category
    {
        return Category::UnknownRisk;
    }

    public function explanation(): RuleExplanation
    {
        return new RuleExplanation(whyItMatters: 'fixture', remediation: 'fixture');
    }

    public function run(ScanContext $context): iterable
    {
        yield from $this->findings;
    }
}

it('aggregates findings from every rule', function () {
    $rules = [
        new FixtureRule('rule-a', [octaneDoctorFinding('rule-a', Severity::Low)]),
        new FixtureRule('rule-b', [
            octaneDoctorFinding('rule-b', Severity::High),
            octaneDoctorFinding('rule-b', Severity::Medium),
        ]),
    ];

    $result = (new Scanner($rules))->scan(new ScanContext(app(), []));

    expect($result->count())->toBe(3);
});

it('sorts findings high severity first', function () {
    $rules = [
        new FixtureRule('rule-low', [octaneDoctorFinding('rule-low', Severity::Low)]),
        new FixtureRule('rule-high', [octaneDoctorFinding('rule-high', Severity::High)]),
        new FixtureRule('rule-med', [octaneDoctorFinding('rule-med', Severity::Medium)]),
    ];

    $result = (new Scanner($rules))->scan(new ScanContext(app(), []));

    expect(array_map(fn (Finding $f) => $f->severity, $result->findings))
        ->toBe([Severity::High, Severity::Medium, Severity::Low]);
});

it('returns an empty result when no rules are registered', function () {
    $result = (new Scanner([]))->scan(new ScanContext(app(), []));

    expect($result->count())->toBe(0)
        ->and($result->durationMs)->toBeGreaterThanOrEqual(0.0);
});
