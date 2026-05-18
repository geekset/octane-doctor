<?php

namespace OctaneDoctor\Tests\Fixtures\RequestInSingleton;

use Illuminate\Http\Request;

class AcceptsRequest
{
    public function __construct(
        protected Request $request,
    ) {}
}
