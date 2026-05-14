<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\Container;

use Illuminate\Contracts\Cache\Repository;

class SafeService
{
    public function __construct(
        protected Repository $cache,
    ) {}
}
