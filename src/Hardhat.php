<?php

namespace Roberts\HardhatLaravel;

class Hardhat
{
    protected static function getFacadeAccessor(): string
    {
        return \Roberts\HardhatLaravel\HardhatWrapper::class;
    }
}
