<?php

namespace OctaneDoctor\Tests\Fixtures\RequestContext;

use Illuminate\Auth\AuthManager;

class HoldsAuthManager
{
    public function __construct(
        protected AuthManager $auth,
    ) {}
}
