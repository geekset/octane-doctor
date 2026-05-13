<?php

namespace Geekset\OctaneDoctor\Tests;

use Geekset\OctaneDoctor\OctaneDoctorServiceProvider;
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
