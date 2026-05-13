<?php

namespace Geekset\OctaneDoctor\Scanning;

use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Rule;

/**
 * Owns the scan lifecycle: invokes each rule against the shared
 * ScanContext, collects findings into a single list, sorts them, and
 * captures the run duration. Rules themselves stay framework-version
 * aware via capability adapters; the Scanner stays dumb on purpose so
 * it can be unit-tested without booting Laravel.
 */
class Scanner
{
    /**
     * @param  array<int, Rule>  $rules
     */
    public function __construct(
        protected array $rules,
    ) {}

    public function scan(ScanContext $context): ScanResult
    {
        $startedAt = microtime(true);

        $findings = [];

        foreach ($this->rules as $rule) {
            foreach ($rule->run($context) as $finding) {
                $findings[] = $finding;
            }
        }

        /*
         * Deterministic ordering: severity desc so the most actionable
         * findings surface first, then rule id / file / line so output
         * is stable across runs and golden-file tests stay reliable.
         */
        usort(
            $findings,
            fn (Finding $a, Finding $b) => $b->severity->weight() <=> $a->severity->weight()
                ?: strcmp($a->ruleId, $b->ruleId)
                ?: strcmp($a->filePath ?? '', $b->filePath ?? '')
                ?: ($a->line ?? 0) <=> ($b->line ?? 0)
        );

        $durationMs = (microtime(true) - $startedAt) * 1000;

        return new ScanResult($findings, $context->paths, $durationMs);
    }
}
