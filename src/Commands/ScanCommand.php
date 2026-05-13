<?php

namespace Geekset\OctaneDoctor\Commands;

use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Scanning\RuleRegistry;
use Geekset\OctaneDoctor\Scanning\ScanContext;
use Geekset\OctaneDoctor\Scanning\Scanner;
use Geekset\OctaneDoctor\Scanning\ScanResult;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;

/*
 * Entry point for `php artisan octane-doctor:scan`. Wires the
 * configured rules and paths into a Scanner run, renders findings to
 * the terminal, and chooses the exit code so the command is usable as
 * a CI gate (spec sections 12.1 and 18).
 */
class ScanCommand extends Command
{
    public $signature = 'octane-doctor:scan
        {--fail-on= : Lowest severity that should fail the run (high, medium, low, info)}';

    public $description = 'Scan the application for Laravel Octane readiness risks.';

    public function handle(Application $app, RuleRegistry $registry): int
    {
        $paths = $this->resolvePaths();

        $context = new ScanContext($app, $paths);

        $scanner = new Scanner($registry->all());

        $result = $scanner->scan($context);

        $this->renderResult($result);

        return $this->exitCode($result);
    }

    /**
     * @return array<int, string>
     */
    /*
     * Drop missing directories silently. Legacy apps frequently keep
     * config entries that no longer exist (custom domain folders,
     * removed modules); failing the scan over a stale path would block
     * adoption without telling the user anything they can act on.
     */
    protected function resolvePaths(): array
    {
        $configured = config('octane-doctor.paths', []);

        return array_values(array_filter($configured, fn ($path) => is_string($path) && is_dir($path)));
    }

    protected function renderResult(ScanResult $result): void
    {
        if ($result->count() === 0) {
            $this->info('No Octane readiness findings detected.');

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
     * string is accepted in config for the same reason (spec section
     * 22, risk: overpromising Octane safety).
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
