<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\RequestContext;

use Illuminate\Contracts\Auth\Guard;

class HoldsAuthGuard
{
    public function __construct(
        protected Guard $guard,
    ) {}
}
