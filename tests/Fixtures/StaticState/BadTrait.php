<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\StaticState;

trait BadTrait
{
    public static ?string $tenant = null;
}
