<?php

namespace Geekset\OctaneDoctor\Commands;

use Geekset\OctaneDoctor\Baseline\Baseline;
use Geekset\OctaneDoctor\Baseline\BaselineRepository;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Scanning\RuleRegistry;
use Geekset\OctaneDoctor\Scanning\ScanContext;
use Geekset\OctaneDoctor\Scanning\Scanner;
use Geekset\OctaneDoctor\Scanning\ScanResult;
use Geekset\OctaneDoctor\Suppression\IgnoreList;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;

/**
 * Entry point for `php artisan octane-doctor:scan`. Wires the
 * configured rules and paths into a Scanner run, renders findings to
 * the terminal, and chooses the exit code so the command is usable as
 * a CI gate.
 */
class ScanCommand extends Command
{
    public $signature = 'octane-doctor:scan
        {--fail-on= : Lowest severity that should fail the run (high, medium, low, info)}
        {--format= : Output format (table, json)}
        {--no-baseline : Ignore the baseline file even if it is configured}';

    public $description = 'Scan the application for Laravel Octane readiness risks.';

    public function handle(Application $app, RuleRegistry $registry, BaselineRepository $repository): int
    {
        $paths = $this->resolvePaths();

        $context = new ScanContext($app, $paths);

        $scanner = new Scanner($registry->all());

        $rawResult = $scanner->scan($context);

        $ignoreList = $this->loadIgnoreList();
        $afterIgnore = $this->filterByIgnore($rawResult, $ignoreList);
        $ignoredCount = $rawResult->count() - $afterIgnore->count();

        $baseline = $this->loadBaseline($repository);
        $filtered = $this->filterByBaseline($afterIgnore, $baseline);
        $baselinedCount = $afterIgnore->count() - $filtered->count();

        match ($this->resolveFormat()) {
            'json' => $this->renderJson($filtered, $baselinedCount, $ignoredCount),
            default => $this->renderTable($filtered, $baselinedCount, $ignoredCount),
        };

        return $this->exitCode($filtered);
    }

    protected function resolveFormat(): string
    {
        $configured = $this->option('format') ?? config('octane-doctor.output', 'table');

        return in_array($configured, ['table', 'json'], true) ? $configured : 'table';
    }

    /**
     * Drop missing directories silently. Legacy apps frequently keep
     * config entries that no longer exist (custom domain folders,
     * removed modules); failing the scan over a stale path would block
     * adoption without telling the user anything they can act on.
     *
     * @return array<int, string>
     */
    protected function resolvePaths(): array
    {
        $configured = config('octane-doctor.paths', []);

        return array_values(array_filter($configured, fn ($path) => is_string($path) && is_dir($path)));
    }

    protected function loadBaseline(BaselineRepository $repository): Baseline
    {
        if ($this->option('no-baseline')) {
            return Baseline::empty();
        }

        $path = config('octane-doctor.baseline');

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return Baseline::empty();
        }

        return $repository->load($path);
    }

    protected function loadIgnoreList(): IgnoreList
    {
        $configured = config('octane-doctor.ignore', []);

        return IgnoreList::fromConfig(is_array($configured) ? $configured : []);
    }

    protected function filterByBaseline(ScanResult $result, Baseline $baseline): ScanResult
    {
        if ($baseline->count() === 0) {
            return $result;
        }

        $remaining = array_values(array_filter(
            $result->findings,
            fn (Finding $finding) => ! $baseline->contains($finding),
        ));

        return new ScanResult($remaining, $result->scannedPaths, $result->durationMs);
    }

    protected function filterByIgnore(ScanResult $result, IgnoreList $ignoreList): ScanResult
    {
        if ($ignoreList->count() === 0) {
            return $result;
        }

        $remaining = array_values(array_filter(
            $result->findings,
            fn (Finding $finding) => ! $ignoreList->contains($finding),
        ));

        return new ScanResult($remaining, $result->scannedPaths, $result->durationMs);
    }

    protected function renderTable(ScanResult $result, int $baselinedCount, int $ignoredCount): void
    {
        if ($result->count() === 0) {
            $this->info('No Octane readiness findings detected.');

            $this->renderSuppressionSummary($baselinedCount, $ignoredCount);

            return;
        }

        foreach ($result->findings as $finding) {
            $this->line('');
            $this->line("[{$finding->severity->value}] {$finding->ruleId}: {$finding->title}");

            if ($finding->filePath !== null) {
                $location = $finding->filePath.($finding->line !== null ? ":{$finding->line}" : '');
                $this->line("  at {$location}");
            }

            $this->line("  {$finding->summary}");
            $this->line("  Why: {$finding->whyItMatters}");
            $this->line("  Fix: {$finding->remediation}");
        }

        $this->line('');
        $counts = $result->countBySeverity();
        $this->line(sprintf(
            'Total: %d (high: %d, medium: %d, low: %d, info: %d) in %.1f ms',
            $result->count(),
            $counts[Severity::High->value],
            $counts[Severity::Medium->value],
            $counts[Severity::Low->value],
            $counts[Severity::Info->value],
            $result->durationMs,
        ));

        $this->renderSuppressionSummary($baselinedCount, $ignoredCount);
    }

    protected function renderSuppressionSummary(int $baselinedCount, int $ignoredCount): void
    {
        if ($baselinedCount > 0) {
            $this->line("Baseline: {$baselinedCount} finding".($baselinedCount === 1 ? '' : 's').' suppressed.');
        }

        if ($ignoredCount > 0) {
            $this->line("Ignore: {$ignoredCount} finding".($ignoredCount === 1 ? '' : 's').' suppressed.');
        }
    }

    protected function renderJson(ScanResult $result, int $baselinedCount, int $ignoredCount): void
    {
        $payload = [
            'schema_version' => '1',
            'summary' => [
                'total' => $result->count(),
                'by_severity' => $result->countBySeverity(),
                'by_category' => $result->countByCategory(),
                'scanned_paths' => $result->scannedPaths,
                'duration_ms' => round($result->durationMs, 3),
                'baselined' => $baselinedCount,
                'ignored' => $ignoredCount,
            ],
            'findings' => array_map(fn ($finding) => $finding->toArray(), $result->findings),
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function exitCode(ScanResult $result): int
    {
        $threshold = $this->resolveThreshold();

        if ($threshold === null) {
            return self::SUCCESS;
        }

        return $result->hasFindingAtOrAbove($threshold) ? self::FAILURE : self::SUCCESS;
    }

    /*
     * Null means "never fail the build" so teams can run the scanner
     * as an informational step before they trust it in CI. The "never"
     * string is accepted in config for the same reason: lets a team
     * adopt the package without committing to a hard gate up front.
     */
    protected function resolveThreshold(): ?Severity
    {
        $configured = $this->option('fail-on') ?? config('octane-doctor.fail_on');

        if ($configured === null || $configured === '' || $configured === 'never') {
            return null;
        }

        return Severity::tryFrom((string) $configured);
    }
}
