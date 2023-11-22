<?php

namespace Mateusjatenee\Persist\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Mateusjatenee\Persist\Persist
 */
class Persist extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Mateusjatenee\Persist\Persist::class;
    }
}
