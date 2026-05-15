<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\StaticState;

use Filament\Resources\Resource;

class SafeFilamentResource extends Resource
{
    protected static ?string $navigationLabel = 'Foo';

    protected static ?string $modelLabel = 'foo';

    protected static ?string $pluralModelLabel = 'foos';

    protected static ?string $navigationIcon = 'heroicon-o-foo';

    protected static ?int $navigationSort = 10;
}
