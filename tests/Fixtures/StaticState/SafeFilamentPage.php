<?php

namespace OctaneDoctor\Tests\Fixtures\StaticState;

use Filament\Pages\Page;

class SafeFilamentPage extends Page
{
    protected static ?string $view = 'filament.pages.foo';

    protected static ?string $navigationIcon = 'heroicon-o-foo';

    protected static ?string $title = 'Foo';

    protected static string $relationship = 'damageReports';
}
