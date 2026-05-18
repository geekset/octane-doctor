<?php

namespace OctaneDoctor\Tests\Fixtures\StaticState;

use Illuminate\Http\Resources\Json\JsonResource;

class UnsafeResourceWithExtraStatic extends JsonResource
{
    public static $wrap = 'data';

    protected static array $cache = [];
}
