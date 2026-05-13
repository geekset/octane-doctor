<?php

namespace Gayansanjeewa\OctaneDoctor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Gayansanjeewa\OctaneDoctor\OctaneDoctor
 */
class OctaneDoctor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Gayansanjeewa\OctaneDoctor\OctaneDoctor::class;
    }
}
