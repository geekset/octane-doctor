<?php

namespace OctaneDoctor\Tests\Fixtures\Container;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Events\Dispatchable;

class SafeEventHoldsContainer
{
    use Dispatchable;

    public function __construct(
        public Container $container,
    ) {}
}
