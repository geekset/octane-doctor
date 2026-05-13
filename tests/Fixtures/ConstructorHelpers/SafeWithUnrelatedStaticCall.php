<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\ConstructorHelpers;

use Illuminate\Support\Facades\Log;

class SafeWithUnrelatedStaticCall
{
    public function __construct()
    {
        Log::debug('Service booted');
    }
}
