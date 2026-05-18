<?php

namespace OctaneDoctor\Tests\Fixtures\StaticState;

use Illuminate\Database\Eloquent\Model;

class SafeEloquentOverrides extends Model
{
    public static $snakeAttributes = false;

    protected static $unguarded = true;
}
