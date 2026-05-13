<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\ConstructorHelpers;

use Illuminate\Contracts\Foundation\Application;

class SafeConstructor
{
    public function __construct(
        protected Application $app,
    ) {}

    public function payload(): array
    {
        return request()->all();
    }
}
