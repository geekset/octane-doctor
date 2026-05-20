<?php

use OctaneDoctor\Ast\FileWalker;
use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\Builtin\MutableStaticState;
use OctaneDoctor\Rules\Builtin\RiskyHelpersInConstructor;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Rules\RuleExplanation;
use OctaneDoctor\Scanning\ScanContext;
use OctaneDoctor\Scanning\Scanner;
use OctaneDoctor\Tests\Support\CountingFileWalker;

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

it('walks each file once even when multiple AST rules are registered', function () {
    $tmp = sys_get_temp_dir().'/octane-doctor-walker-'.bin2hex(random_bytes(4));
    mkdir($tmp, 0o755, true);
    file_put_contents($tmp.'/Foo.php', "<?php\nclass Foo { public static array \$cache = []; }\n");
    file_put_contents($tmp.'/Bar.php', "<?php\nclass Bar { public static array \$cache = []; }\n");

    try {
        $walker = new CountingFileWalker;

        $rules = [
            new MutableStaticState(new FileWalker),
            new RiskyHelpersInConstructor(new FileWalker),
        ];

        $result = (new Scanner($rules, $walker))->scan(new ScanContext(app(), [$tmp]));

        // Two AST rules, two files: only one walk should have happened.
        expect($walker->walkCalls)->toBe(1)
            ->and($walker->yieldedFiles)->toBe(2)
            ->and($result->count())->toBeGreaterThanOrEqual(2);
    } finally {
        @unlink($tmp.'/Foo.php');
        @unlink($tmp.'/Bar.php');
        @rmdir($tmp);
    }
});
