<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\Container;

use Illuminate\Foundation\Application;

class HoldsApplicationProperty
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }
}
