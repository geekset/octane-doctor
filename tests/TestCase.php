<?php

namespace OctaneDoctor\Tests;

use OctaneDoctor\OctaneDoctorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            OctaneDoctorServiceProvider::class,
        ];
    }
}
