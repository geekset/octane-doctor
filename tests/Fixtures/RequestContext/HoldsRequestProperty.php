<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\RequestContext;

use Illuminate\Http\Request;

class HoldsRequestProperty
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
