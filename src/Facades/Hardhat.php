<?php

namespace Roberts\HardhatLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string compile()
 * @method static string runScript(string $scriptPath, array $args = [], array $env = [])
 * @method static string runCommand(string $command, array $args = [], array $env = [])
 *
 * @see \Roberts\HardhatLaravel\HardhatWrapper
 */
class Hardhat extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Roberts\HardhatLaravel\HardhatWrapper::class;
    }
}
