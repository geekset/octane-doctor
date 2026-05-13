<?php

namespace Geekset\OctaneDoctor;

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
            ->hasCommand(ScanCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RuleRegistry::class, function (Application $app) {
            return new RuleRegistry(
                $app,
                (array) config('octane-doctor.rules', []),
                (array) config('octane-doctor.custom_rules', []),
            );
        });
    }
}
