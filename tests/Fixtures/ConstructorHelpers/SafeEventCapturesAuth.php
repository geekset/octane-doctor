<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\ConstructorHelpers;

use Illuminate\Foundation\Events\Dispatchable;

class SafeEventCapturesAuth
{
    use Dispatchable;

    public $causer;

    public function __construct()
    {
        $this->causer = auth()->user();
    }
}
