<?php

namespace OctaneDoctor\Tests\Fixtures\RequestInSingleton;

use Illuminate\Contracts\Auth\Guard;

class AcceptsGuard
{
    public function __construct(
        protected Guard $guard,
    ) {}
}
