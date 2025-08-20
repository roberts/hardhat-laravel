<?php

namespace Roberts\HardhatLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use Roberts\HardhatLaravel\HardhatWrapper;

/**
 * @see HardhatWrapper
 */
class HardhatLaravel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
     return HardhatWrapper::class;
    }
}
