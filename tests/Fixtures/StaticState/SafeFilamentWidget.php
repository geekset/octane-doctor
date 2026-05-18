<?php

namespace OctaneDoctor\Tests\Fixtures\StaticState;

use Filament\Widgets\ChartWidget;

class SafeFilamentWidget extends ChartWidget
{
    protected static ?int $sort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
}
