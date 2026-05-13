<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\RequestContext;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class SafeFormRequestHoldsRequest extends FormRequest
{
    protected ?Request $delegate = null;
}
