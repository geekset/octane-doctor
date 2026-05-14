<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\Container;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class SafeServiceProviderHoldsApp extends ServiceProvider
{
    protected Application $app;
}
