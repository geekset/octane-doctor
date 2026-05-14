<?php

use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Suppression\IgnoreList;

function makeIgnoreFinding(string $ruleId, string $summary): Finding
{
    return new Finding(
        ruleId: $ruleId,
        title: 't',
        severity: Severity::Medium,
        category: Category::UnknownRisk,
        summary: $summary,
        whyItMatters: 'w',
        remediation: 'r',
        filePath: "/app/{$summary}.php",
    );
}

it('matches a finding when its rule id is in the list', function () {
    $list = new IgnoreList(['mutable-static-state']);
    $finding = makeIgnoreFinding('mutable-static-state', 'X');

    expect($list->contains($finding))->toBeTrue();
});

it('matches a finding when its fingerprint is in the list', function () {
    $finding = makeIgnoreFinding('some-rule', 'X');

    $list = new IgnoreList([$finding->fingerprint()]);

    expect($list->contains($finding))->toBeTrue();
});

it('does not match unrelated findings', function () {
    $list = new IgnoreList(['mutable-static-state']);
    $finding = makeIgnoreFinding('request-in-singleton', 'X');

    expect($list->contains($finding))->toBeFalse();
});

it('returns false for an empty list', function () {
    $finding = makeIgnoreFinding('some-rule', 'X');

    expect(IgnoreList::empty()->contains($finding))->toBeFalse();
});

it('filters non-string and empty entries when built from config', function () {
    $list = IgnoreList::fromConfig(['mutable-static-state', '', 0, null, false, 'abc1234567890def']);

    expect($list->count())->toBe(2)
        ->and($list->entries)->toBe(['mutable-static-state', 'abc1234567890def']);
});
