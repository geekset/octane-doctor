<?php

namespace OctaneDoctor\Rules\Builtin;

use Illuminate\Contracts\Foundation\Application;
use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\RiskClass;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Rules\RuleExplanation;
use OctaneDoctor\Scanning\ScanContext;

/**
 * Sanity checks on the host application's Octane setup. Two layered
 * signals, deliberately conservative:
 *
 * - Octane is not installed at all. Emitted as Info so a readiness
 *   scan still surfaces it without producing a build failure.
 * - Octane is installed but the user has not published its config.
 *   Low severity. The application is running on package defaults
 *   that may change between Octane versions and that cannot be
 *   reviewed in PRs.
 *
 * The rule never inspects runtime env or guesses the Octane server,
 * and it exits early after the first applicable signal so a single
 * scan emits at most one octane-config-check finding.
 *
 * We deliberately do not flag missing entries in `octane.flush`.
 * Laravel Octane resets the core framework state (auth state, the
 * `request` binding, database connections, and so on) inside
 * `Octane::prepareApplicationForNextOperation`, independently of the
 * user `octane.flush` array. That config key is for the host
 * application to flag *additional* singletons it owns and wants
 * rebuilt per request, not a knob for the framework defaults. The
 * other rules in this pack already catch the kind of singletons
 * that belong there.
 */
class OctaneConfigCheck implements Rule
{
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
        return Severity::Low;
    }

    public function category(): Category
    {
        return Category::Configuration;
    }

    public function riskClass(): RiskClass
    {
        return RiskClass::RequestScopeMisuse;
    }

    public function explanation(): RuleExplanation
    {
        return new RuleExplanation(
            whyItMatters: 'Two layered signals about the Octane setup itself: not installed at all (informational), or installed without a published config (defaults can shift between Octane versions and the flush, warm, and listener lists cannot be reviewed in PRs).',
            remediation: 'Install laravel/octane when you intend to run on Octane, then publish the config so settings are explicit and version controlled.',
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

    protected function notInstalledFinding(): Finding
    {
        return new Finding(
            ruleId: $this->id(),
            title: 'Laravel Octane is not installed',
            severity: Severity::Info,
            category: $this->category(),
            riskClass: $this->riskClass(),
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
            riskClass: $this->riskClass(),
            summary: 'config/octane.php is missing.',
            whyItMatters: 'Octane is installed but the application is running entirely on the package defaults. Defaults can shift across Octane versions, and you cannot customise the flush, warm, or listener lists without publishing the config.',
            remediation: 'Run php artisan vendor:publish --provider="Laravel\\Octane\\OctaneServiceProvider" --tag=octane-config to publish the config file, then commit it so the application has explicit, reviewable Octane settings.',
            filePath: $configPath,
        );
    }
}
