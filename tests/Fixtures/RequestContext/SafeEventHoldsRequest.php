<?php

namespace OctaneDoctor\Tests\Fixtures\RequestContext;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

class SafeEventHoldsRequest
{
    use Dispatchable;

    public function __construct(
        public Request $request,
    ) {}
}
