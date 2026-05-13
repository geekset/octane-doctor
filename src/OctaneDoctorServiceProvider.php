<?php

namespace Gayansanjeewa\OctaneDoctor;

use Gayansanjeewa\OctaneDoctor\Commands\OctaneDoctorCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class OctaneDoctorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('octane-doctor')
            ->hasConfigFile()
            ->hasCommand(OctaneDoctorCommand::class);
    }
}
