<?php

use OctaneDoctor\Baseline\Baseline;
use OctaneDoctor\Baseline\BaselineRepository;
use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\RiskClass;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/octane-doctor-baseline-'.uniqid().'.json';
});

afterEach(function () {
    if (is_file($this->path)) {
        unlink($this->path);
    }
});

function makeBaselineFinding(string $summary): Finding
{
    return new Finding(
        ruleId: 'test',
        title: 'test',
        severity: Severity::Medium,
        category: Category::UnknownRisk,
        riskClass: RiskClass::DataLeak,
        summary: $summary,
        whyItMatters: 'why',
        remediation: 'fix',
        filePath: '/app/'.$summary.'.php',
    );
}

it('returns an empty baseline when the file does not exist', function () {
    $repo = new BaselineRepository;

    $baseline = $repo->load($this->path);

    expect($baseline)->toBeInstanceOf(Baseline::class)
        ->and($baseline->count())->toBe(0);
});

it('writes a baseline file with deterministic fingerprints', function () {
    $repo = new BaselineRepository;

    $findings = [
        makeBaselineFinding('alpha'),
        makeBaselineFinding('beta'),
        makeBaselineFinding('alpha'),
    ];

    $baseline = $repo->save($this->path, $findings);

    expect($baseline->count())->toBe(2)
        ->and(is_file($this->path))->toBeTrue();

    $decoded = json_decode(file_get_contents($this->path), true);

    expect($decoded)
        ->toHaveKey('schema_version', '2')
        ->toHaveKey('fingerprint_count', 2)
        ->and($decoded['fingerprints'])->toHaveCount(2);
});

it('loads the saved fingerprints back', function () {
    $repo = new BaselineRepository;

    $finding = makeBaselineFinding('alpha');
    $repo->save($this->path, [$finding]);

    $baseline = $repo->load($this->path);

    expect($baseline->count())->toBe(1)
        ->and($baseline->contains($finding))->toBeTrue();
});

it('returns empty when the file is malformed', function () {
    file_put_contents($this->path, 'not json');

    $baseline = (new BaselineRepository)->load($this->path);

    expect($baseline->count())->toBe(0);
});
