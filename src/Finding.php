<?php

namespace Geekset\OctaneDoctor;

use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;

final readonly class Finding
{
    public function __construct(
        public string $ruleId,
        public string $title,
        public Severity $severity,
        public Category $category,
        public string $summary,
        public string $whyItMatters,
        public string $remediation,
        public ?string $filePath = null,
        public ?int $line = null,
        public ?string $symbol = null,
        public bool $autofixable = false,
        public ?string $autofixStrategyId = null,
        public ?string $docsUrl = null,
    ) {}

    public function fingerprint(): string
    {
        $parts = [
            $this->ruleId,
            $this->filePath ?? '',
            $this->symbol ?? '',
            $this->summary,
        ];

        return substr(hash('sha256', implode('|', $parts)), 0, 16);
    }

    /**
     * @return array{
     *     rule_id: string,
     *     title: string,
     *     severity: string,
     *     category: string,
     *     summary: string,
     *     why_it_matters: string,
     *     remediation: string,
     *     file_path: ?string,
     *     line: ?int,
     *     symbol: ?string,
     *     autofixable: bool,
     *     autofix_strategy_id: ?string,
     *     docs_url: ?string,
     *     fingerprint: string
     * }
     */
    public function toArray(): array
    {
        return [
            'rule_id' => $this->ruleId,
            'title' => $this->title,
            'severity' => $this->severity->value,
            'category' => $this->category->value,
            'summary' => $this->summary,
            'why_it_matters' => $this->whyItMatters,
            'remediation' => $this->remediation,
            'file_path' => $this->filePath,
            'line' => $this->line,
            'symbol' => $this->symbol,
            'autofixable' => $this->autofixable,
            'autofix_strategy_id' => $this->autofixStrategyId,
            'docs_url' => $this->docsUrl,
            'fingerprint' => $this->fingerprint(),
        ];
    }
}
