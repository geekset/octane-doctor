<?php

namespace OctaneDoctor\Tests\Fixtures\RequestInSingleton;

use Illuminate\Contracts\Cache\Repository;

class AcceptsCache
{
    public function __construct(
        protected Repository $cache,
    ) {}
}
