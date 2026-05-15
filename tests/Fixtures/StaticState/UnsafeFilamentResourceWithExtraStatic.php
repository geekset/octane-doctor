<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\StaticState;

use Filament\Resources\Resource;

class UnsafeFilamentResourceWithExtraStatic extends Resource
{
    protected static ?string $navigationLabel = 'Foo';

    protected static array $cache = [];
}
