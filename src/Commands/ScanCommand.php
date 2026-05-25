<?php

namespace OctaneDoctor\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use OctaneDoctor\Baseline\Baseline;
use OctaneDoctor\Baseline\BaselineRepository;
use OctaneDoctor\Commands\Concerns\ResolvesScanPaths;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Scanning\RuleRegistry;
use OctaneDoctor\Scanning\ScanContext;
use OctaneDoctor\Scanning\Scanner;
use OctaneDoctor\Scanning\ScanResult;
use OctaneDoctor\Suppression\IgnoreList;
use Termwind\Termwind;

use function Termwind\render;

/**
 * Entry point for `php artisan octane-doctor:scan`. Wires the
 * configured rules and paths into a Scanner run, renders findings to
 * the terminal, and chooses the exit code so the command is usable as
 * a CI gate.
 */
class ScanCommand extends Command
{
    use ResolvesScanPaths;

    public $signature = 'octane-doctor:scan
        {--fail-on= : Lowest severity that should fail the run (high, medium, low, info)}
        {--format= : Output format (table, json)}
        {--no-baseline : Ignore the baseline file even if it is configured}
        {--rule= : Run only the rule with the given id (see octane-doctor:rules:list)}';

    public $description = 'Scan the application for Laravel Octane readiness risks.';

    public function handle(Application $app, RuleRegistry $registry, BaselineRepository $repository): int
    {
        $format = $this->resolveFormat();
        $isJson = $format === 'json';

        /*
         * Buffer stray writes when emitting JSON so anything a rule
         * or container resolution echoes during the scan ends up on
         * STDERR instead of corrupting the machine-readable payload.
         */
        if ($isJson) {
            ini_set('display_errors', 'stderr');
            ob_start();
        }

        $pathInfo = $this->resolvePathInfo();

        $rules = $this->resolveRules($registry);

        if ($rules === null) {
            return self::FAILURE;
        }

        $context = new ScanContext($app, $pathInfo['resolved'], $app->basePath());

        $scanner = new Scanner($rules);

        $rawResult = $scanner->scan($context);

        $ignoreList = $this->loadIgnoreList();
        $afterIgnore = $this->filterByIgnore($rawResult, $ignoreList);
        $ignoredCount = $rawResult->count() - $afterIgnore->count();

        $baseline = $this->loadBaseline($repository);
        $filtered = $this->filterByBaseline($afterIgnore, $baseline);
        $baselinedCount = $afterIgnore->count() - $filtered->count();

        if ($isJson) {
            $stray = ob_get_clean();

            if (is_string($stray) && $stray !== '' && defined('STDERR')) {
                fwrite(STDERR, $stray);
            }

            $this->renderJson($filtered, $baselinedCount, $ignoredCount, $pathInfo['missing']);
        } else {
            $this->renderTable($filtered, $baselinedCount, $ignoredCount, $pathInfo['missing']);
        }

        return $this->exitCode($filtered);
    }

    protected function resolveFormat(): string
    {
        $configured = $this->option('format') ?? config('octane-doctor.output', 'table');

        return in_array($configured, ['table', 'json'], true) ? $configured : 'table';
    }

    /**
     * Resolve which rules to run. Returns null when the user asked
     * for a rule id that does not exist; the caller exits cleanly
     * with a non-zero status so the failure is visible in CI.
     *
     * @return array<int, Rule>|null
     */
    protected function resolveRules(RuleRegistry $registry): ?array
    {
        $rules = $registry->all();

        $ruleId = $this->option('rule');

        if (! is_string($ruleId) || $ruleId === '') {
            return $rules;
        }

        foreach ($rules as $rule) {
            if ($rule->id() === $ruleId) {
                return [$rule];
            }
        }

        $this->error("No rule registered with id '{$ruleId}'.");
        $this->line('Run octane-doctor:rules:list to see every registered rule.');

        return null;
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

    /**
     * @param  array<int, string>  $missingPaths
     */
    protected function renderTable(ScanResult $result, int $baselinedCount, int $ignoredCount, array $missingPaths): void
    {
        Termwind::renderUsing($this->output);

        $this->renderMissingPathWarnings($missingPaths);

        if ($result->count() === 0) {
            render(<<<'HTML'
                <div class="mx-2 my-1">
                    <span class="px-1 bg-green-600 text-white font-bold">PASS</span>
                    <span class="ml-1">No Octane readiness findings detected.</span>
                </div>
            HTML);

            $this->renderSuppressionSummary($baselinedCount, $ignoredCount);

            return;
        }

        foreach ($result->findings as $finding) {
            $this->renderFinding($finding);
        }

        $this->renderFooter($result);

        $this->renderSuppressionSummary($baselinedCount, $ignoredCount);
    }

    /**
     * @param  array<int, string>  $missingPaths
     */
    protected function renderMissingPathWarnings(array $missingPaths): void
    {
        foreach ($missingPaths as $path) {
            $escaped = $this->escape($path);

            render(<<<HTML
                <div class="mx-2 mt-1">
                    <span class="px-1 bg-yellow-500 text-black font-bold">WARNING</span>
                    <span class="ml-1">configured scan path does not exist: {$escaped}</span>
                </div>
            HTML);
        }
    }

    protected function renderFinding(Finding $finding): void
    {
        [$badgeBg, $badgeText] = match ($finding->severity) {
            Severity::High => ['bg-red-600', 'text-white'],
            Severity::Medium => ['bg-yellow-500', 'text-black'],
            Severity::Low => ['bg-blue-500', 'text-white'],
            Severity::Info => ['bg-gray-500', 'text-white'],
        };

        $severity = strtoupper($finding->severity->value);
        $ruleId = $this->escape($finding->ruleId);
        $title = $this->escape($finding->title);

        render(<<<HTML
            <div class="mx-2 mt-1">
                <span class="px-1 {$badgeBg} {$badgeText} font-bold">{$severity}</span>
                <span class="ml-1 font-bold">{$ruleId}</span>
                <span class="ml-1 text-gray">{$title}</span>
            </div>
        HTML);

        if ($finding->filePath !== null) {
            $location = $finding->filePath.($finding->line !== null ? ":{$finding->line}" : '');
            $location = $this->escape($location);

            render(<<<HTML
                <div class="mx-2 ml-4 text-gray">at {$location}</div>
            HTML);
        }

        $summary = $this->escape($finding->summary);
        $why = $this->escape($finding->whyItMatters);
        $fix = $this->escape($finding->remediation);

        render(<<<HTML
            <div class="mx-2 ml-4">{$summary}</div>
        HTML);

        render(<<<HTML
            <div class="mx-2 ml-4 text-gray"><span class="font-bold text-yellow">Why:</span> {$why}</div>
        HTML);

        render(<<<HTML
            <div class="mx-2 ml-4 text-gray"><span class="font-bold text-green">Fix:</span> {$fix}</div>
        HTML);
    }

    protected function renderFooter(ScanResult $result): void
    {
        $counts = $result->countBySeverity();
        $duration = sprintf('%.1f', $result->durationMs);
        $total = $result->count();
        $high = $counts[Severity::High->value];
        $medium = $counts[Severity::Medium->value];
        $low = $counts[Severity::Low->value];
        $info = $counts[Severity::Info->value];

        render(<<<HTML
            <div class="mx-2 mt-1">
                <span class="font-bold">Total:</span>
                <span class="ml-1">{$total}</span>
                <span class="ml-1 text-gray">(high: {$high}, medium: {$medium}, low: {$low}, info: {$info})</span>
                <span class="ml-1 text-gray">in {$duration} ms</span>
            </div>
        HTML);
    }

    protected function renderSuppressionSummary(int $baselinedCount, int $ignoredCount): void
    {
        if ($baselinedCount > 0) {
            $word = $baselinedCount === 1 ? 'finding' : 'findings';
            render(<<<HTML
                <div class="mx-2 text-gray">Baseline: {$baselinedCount} {$word} suppressed.</div>
            HTML);
        }

        if ($ignoredCount > 0) {
            $word = $ignoredCount === 1 ? 'finding' : 'findings';
            render(<<<HTML
                <div class="mx-2 text-gray">Ignore: {$ignoredCount} {$word} suppressed.</div>
            HTML);
        }
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param  array<int, string>  $missingPaths
     */
    protected function renderJson(ScanResult $result, int $baselinedCount, int $ignoredCount, array $missingPaths): void
    {
        $warnings = array_map(
            fn (string $path) => [
                'code' => 'missing-scan-path',
                'message' => "configured scan path does not exist: {$path}",
                'path' => $path,
            ],
            $missingPaths,
        );

        $payload = [
            'schema_version' => '1',
            'summary' => [
                'total' => $result->count(),
                'by_severity' => $result->countBySeverity(),
                'by_category' => $result->countByCategory(),
                'by_risk_class' => $result->countByRiskClass(),
                'scanned_paths' => $result->scannedPaths,
                'duration_ms' => round($result->durationMs, 3),
                'baselined' => $baselinedCount,
                'ignored' => $ignoredCount,
            ],
            'warnings' => $warnings,
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
