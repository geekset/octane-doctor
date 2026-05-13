<?php

namespace Geekset\OctaneDoctor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Geekset\OctaneDoctor\OctaneDoctor
 */
class OctaneDoctor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Geekset\OctaneDoctor\OctaneDoctor::class;
    }
}
