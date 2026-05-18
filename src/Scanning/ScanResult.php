<?php

namespace OctaneDoctor\Scanning;

use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;

final readonly class ScanResult
{
    /**
     * @param  array<int, Finding>  $findings
     * @param  array<int, string>  $scannedPaths
     */
    public function __construct(
        public array $findings,
        public array $scannedPaths,
        public float $durationMs,
    ) {}

    public function count(): int
    {
        return count($this->findings);
    }

    /**
     * @return array<string, int>
     */
    public function countBySeverity(): array
    {
        $counts = [
            Severity::High->value => 0,
            Severity::Medium->value => 0,
            Severity::Low->value => 0,
            Severity::Info->value => 0,
        ];

        foreach ($this->findings as $finding) {
            $counts[$finding->severity->value]++;
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function countByCategory(): array
    {
        $counts = [];

        foreach ($this->findings as $finding) {
            $counts[$finding->category->value] = ($counts[$finding->category->value] ?? 0) + 1;
        }

        return $counts;
    }

    public function hasFindingAtOrAbove(Severity $threshold): bool
    {
        foreach ($this->findings as $finding) {
            if ($finding->severity->isAtLeast($threshold)) {
                return true;
            }
        }

        return false;
    }
}
