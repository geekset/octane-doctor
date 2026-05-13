<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\StaticState;

use Illuminate\Http\Resources\Json\JsonResource;

class SafeResourceWrap extends JsonResource
{
    public static $wrap = 'data';
}
