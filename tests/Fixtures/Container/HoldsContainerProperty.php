<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\Container;

use Illuminate\Contracts\Container\Container;

class HoldsContainerProperty
{
    public function __construct(
        protected Container $container,
    ) {}
}
