<?php

namespace Geekset\OctaneDoctor;

use Geekset\OctaneDoctor\Commands\BaselineCommand;
use Geekset\OctaneDoctor\Commands\ScanCommand;
use Geekset\OctaneDoctor\Scanning\RuleRegistry;
use Illuminate\Contracts\Foundation\Application;
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
            ->hasCommand(BaselineCommand::class);
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
