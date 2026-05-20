<?php

namespace OctaneDoctor;

use Illuminate\Contracts\Foundation\Application;
use OctaneDoctor\Commands\BaselineCommand;
use OctaneDoctor\Commands\RulesListCommand;
use OctaneDoctor\Commands\RulesViewCommand;
use OctaneDoctor\Commands\ScanCommand;
use OctaneDoctor\Scanning\RuleRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class OctaneDoctorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('octane-doctor')
            ->hasConfigFile()
            ->hasCommand(ScanCommand::class)
            ->hasCommand(BaselineCommand::class)
            ->hasCommand(RulesListCommand::class)
            ->hasCommand(RulesViewCommand::class);
    }

    public function packageBooted(): void
    {
        /*
         * Pre-boot warning routing: when the host invokes the scan
         * command with --format=json, the JSON payload is the only
         * thing that should land on STDOUT. Laravel boot runs before
         * our command handle() is reached, so any deprecations fired
         * while loading host config files would otherwise corrupt the
         * JSON. Redirecting display_errors to STDERR at provider boot
         * time keeps the JSON parseable for CI consumers.
         */
        if ($this->commandLineRequestsJson()) {
            ini_set('display_errors', 'stderr');
        }
    }

    protected function commandLineRequestsJson(): bool
    {
        if (PHP_SAPI !== 'cli') {
            return false;
        }

        $argv = $_SERVER['argv'] ?? [];

        if (! is_array($argv)) {
            return false;
        }

        $hasCommand = false;
        $hasJsonFormat = false;

        foreach ($argv as $argument) {
            if (! is_string($argument)) {
                continue;
            }

            if ($argument === 'octane-doctor:scan') {
                $hasCommand = true;
            }

            if ($argument === '--format=json' || $argument === '--format json') {
                $hasJsonFormat = true;
            }
        }

        return $hasCommand && $hasJsonFormat;
    }

    public function packageRegistered(): void
    {
        /*
         * Bind, not singleton: tests and long-running processes can
         * change the rule list at runtime, and we want each resolve
         * to re-read config so the registry reflects the current
         * configuration instead of the values that happened to be set
         * the first time the registry was needed.
         */
        $this->app->bind(RuleRegistry::class, function (Application $app) {
            return new RuleRegistry(
                $app,
                (array) config('octane-doctor.rules', []),
                (array) config('octane-doctor.custom_rules', []),
            );
        });
    }
}
