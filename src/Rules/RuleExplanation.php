<?php

namespace OctaneDoctor\Rules;

final readonly class RuleExplanation
{
    /**
     * @param  array<int, string>  $examples
     */
    public function __construct(
        public string $whyItMatters,
        public string $remediation,
        public array $examples = [],
        public ?string $docsUrl = null,
    ) {}
}
