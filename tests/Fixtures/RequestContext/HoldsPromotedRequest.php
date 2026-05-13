<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\RequestContext;

use Illuminate\Http\Request;

class HoldsPromotedRequest
{
    public function __construct(
        protected Request $request,
    ) {}
}
