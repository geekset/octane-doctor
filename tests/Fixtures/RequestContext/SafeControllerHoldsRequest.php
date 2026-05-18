<?php

namespace OctaneDoctor\Tests\Fixtures\RequestContext;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SafeControllerHoldsRequest extends Controller
{
    public function __construct(
        protected Request $request,
    ) {}
}
