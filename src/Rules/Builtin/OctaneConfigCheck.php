<?php

namespace Geekset\OctaneDoctor\Rules\Builtin;

use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Rule;
use Geekset\OctaneDoctor\Rules\RuleExplanation;
use Geekset\OctaneDoctor\Scanning\ScanContext;
use Illuminate\Contracts\Foundation\Application;

/**
 * Sanity checks on the host application's Octane setup. Three signals:
 *
 * - Octane is not installed at all. Emitted as Info so a readiness
 *   scan still surfaces it.
 * - Octane is installed but the user has not published its config.
 *   Low severity. Means any tweak they want is hidden behind defaults
 *   that may change between Octane versions.
 * - Octane config is published but the flush array is missing
 *   commonly state-bearing services. Medium severity.
 *
 * Deliberately conservative: each check is one finding maximum, and
 * the rule never inspects runtime env or guesses Octane server
 * choice. It exits early when the host clearly is not on Octane yet.
 */
class OctaneConfigCheck implements Rule
{
    /**
     * Services Octane's default flush array clears between requests.
     * If a custom flush array drops one of these without replacement,
     * scoped state from that service can leak across requests.
     *
     * @var array<int, string>
     */
    protected const BASELINE_FLUSH_SERVICES = [
        'auth.driver',
        'cache',
        'cookie',
        'db',
        'db.factory',
        'db.transactions',
        'hash',
        'translator',
        'view',
    ];

    public function id(): string
    {
        return 'octane-config-check';
    }

    public function title(): string
    {
        return 'Octane configuration sanity check';
    }

    public function severity(): Severity
    {
        return Severity::Medium;
    }

    public function category(): Category
    {
        return Category::Configuration;
    }

    public function explanation(): RuleExplanation
    {
        return new RuleExplanation(
            whyItMatters: 'Three layered signals about the Octane setup itself: not installed at all (informational), installed without a published config (defaults can shift between Octane versions), and a custom octane.flush array that drops baseline state bearing services (worker keeps the first request\'s instance of auth, cache, db, etc.).',
            remediation: 'Install laravel/octane when you intend to run on Octane, publish the config so settings are explicit, and keep the baseline services (auth.driver, cache, cookie, db, db.factory, db.transactions, hash, translator, view) in octane.flush.',
            examples: [
                'composer require laravel/octane',
                'php artisan vendor:publish --provider="Laravel\\Octane\\OctaneServiceProvider" --tag=octane-config',
            ],
        );
    }

    public function run(ScanContext $context): iterable
    {
        $app = $context->app;

        if (! $this->octaneInstalled($app)) {
            yield $this->notInstalledFinding();

            return;
        }

        $configPath = $app->configPath('octane.php');

        if (! is_file($configPath)) {
            yield $this->configMissingFinding($configPath);

            return;
        }

        $flush = $this->resolveFlushList($app);

        if ($flush === null) {
            return;
        }

        $missing = array_values(array_diff(self::BASELINE_FLUSH_SERVICES, $flush));

        if ($missing === []) {
            return;
        }

        yield $this->incompleteFlushFinding($configPath, $missing);
    }

    protected function octaneInstalled(Application $app): bool
    {
        $composerPath = $app->basePath('composer.json');

        if (! is_file($composerPath)) {
            return false;
        }

        $contents = @file_get_contents($composerPath);

        if ($contents === false) {
            return false;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return false;
        }

        $require = $decoded['require'] ?? [];
        $requireDev = $decoded['require-dev'] ?? [];

        return isset($require['laravel/octane']) || isset($requireDev['laravel/octane']);
    }

    /**
     * @return array<int, string>|null
     */
    protected function resolveFlushList(Application $app): ?array
    {
        $flush = $app['config']->get('octane.flush');

        if (! is_array($flush)) {
            return null;
        }

        return array_values(array_filter($flush, fn ($entry) => is_string($entry)));
    }

    protected function notInstalledFinding(): Finding
    {
        return new Finding(
            ruleId: $this->id(),
            title: 'Laravel Octane is not installed',
            severity: Severity::Info,
            category: $this->category(),
            summary: 'laravel/octane is not listed in composer.json.',
            whyItMatters: 'The other rules in this scan describe how this application would behave under Octane, but the host application is not actually running Octane yet. This is fine if you are still in the assessment phase; it is a problem if you expected Octane to already be in use.',
            remediation: 'If you intend to run on Octane, install it with composer require laravel/octane and follow the official setup. If you only wanted a readiness assessment, you can ignore this finding.',
        );
    }

    protected function configMissingFinding(string $configPath): Finding
    {
        return new Finding(
            ruleId: $this->id(),
            title: 'Octane config has not been published',
            severity: Severity::Low,
            category: $this->category(),
            summary: 'config/octane.php is missing.',
            whyItMatters: 'Octane is installed but the application is running entirely on the package defaults. Defaults can shift across Octane versions, and you cannot customise the flush, warm, or listener lists without publishing the config.',
            remediation: 'Run php artisan vendor:publish --provider="Laravel\\Octane\\OctaneServiceProvider" --tag=octane-config to publish the config file, then commit it so the application has explicit, reviewable Octane settings.',
            filePath: $configPath,
        );
    }

    /**
     * @param  array<int, string>  $missing
     */
    protected function incompleteFlushFinding(string $configPath, array $missing): Finding
    {
        $missingList = implode(', ', $missing);

        return new Finding(
            ruleId: $this->id(),
            title: 'Octane flush list is missing common services',
            severity: $this->severity(),
            category: $this->category(),
            summary: "octane.flush does not include: {$missingList}.",
            whyItMatters: 'Anything in octane.flush is rebuilt between requests, so leaving common state-bearing services out means a worker keeps the first request\'s instance of those services for the rest of its life. The most common consequence is cross-request auth state and stale database connections.',
            remediation: "Add the missing services back to the flush array in config/octane.php (or remove your overrides so the package default applies). Missing entries: {$missingList}.",
            filePath: $configPath,
        );
    }
}
