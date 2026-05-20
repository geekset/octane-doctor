<?php

namespace OctaneDoctor\Tests\Fixtures\RequestInSingleton;

use Illuminate\Auth\AuthManager;

class AcceptsAuthManager
{
    public function __construct(
        protected AuthManager $auth,
    ) {}
}
