<?php

namespace OctaneDoctor;

use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\RiskClass;
use OctaneDoctor\Enums\Severity;

final readonly class Finding
{
    public function __construct(
        public string $ruleId,
        public string $title,
        public Severity $severity,
        public Category $category,
        public RiskClass $riskClass,
        public string $summary,
        public string $whyItMatters,
        public string $remediation,
        public ?string $filePath = null,
        public ?int $line = null,
        public ?string $symbol = null,
        public ?string $docsUrl = null,
    ) {}

    public function withFilePath(?string $filePath): self
    {
        return new self(
            ruleId: $this->ruleId,
            title: $this->title,
            severity: $this->severity,
            category: $this->category,
            riskClass: $this->riskClass,
            summary: $this->summary,
            whyItMatters: $this->whyItMatters,
            remediation: $this->remediation,
            filePath: $filePath,
            line: $this->line,
            symbol: $this->symbol,
            docsUrl: $this->docsUrl,
        );
    }

    public function relativizeFilePath(string $basePath): self
    {
        if ($this->filePath === null || $basePath === '') {
            return $this;
        }

        $normalizedBase = rtrim(str_replace('\\', '/', $basePath), '/').'/';
        $normalizedPath = str_replace('\\', '/', $this->filePath);

        if (! str_starts_with($normalizedPath, $normalizedBase)) {
            return $this;
        }

        return $this->withFilePath(substr($normalizedPath, strlen($normalizedBase)));
    }

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
     *     risk_class: string,
     *     summary: string,
     *     why_it_matters: string,
     *     remediation: string,
     *     file_path: ?string,
     *     line: ?int,
     *     symbol: ?string,
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
            'risk_class' => $this->riskClass->value,
            'summary' => $this->summary,
            'why_it_matters' => $this->whyItMatters,
            'remediation' => $this->remediation,
            'file_path' => $this->filePath,
            'line' => $this->line,
            'symbol' => $this->symbol,
            'docs_url' => $this->docsUrl,
            'fingerprint' => $this->fingerprint(),
        ];
    }
}
