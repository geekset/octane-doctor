<?php

namespace Geekset\OctaneDoctor\Scanning;

use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Rule;

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
