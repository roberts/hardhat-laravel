<?php

namespace Roberts\HardhatLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Roberts\HardhatLaravel\HardhatLaravel
 */
class HardhatLaravel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Roberts\HardhatLaravel\HardhatLaravel::class;
    }
}
