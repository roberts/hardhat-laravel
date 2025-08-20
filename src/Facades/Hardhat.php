<?php

namespace Roberts\HardhatLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use Roberts\HardhatLaravel\HardhatWrapper;

/**
 * @method static string compile()
 * @method static string runScript(string $scriptPath, array $args = [], array $env = [])
 * @method static string runCommand(string $command, array $args = [], array $env = [])
 * @method static string clean()
 * @method static string test(array $args = [], array $env = [])
 * @method static string node(array $args = [], array $env = [])
 * @method static string help(string $subcommand = null)
 * @method static \Roberts\HardhatLaravel\Support\HardhatResult tryRun(string $command, array $args = [], array $env = [])
 * @method static \Roberts\HardhatLaravel\Support\HardhatResult tryRunScript(string $scriptPath, array $args = [], array $env = [])
 *
 * @see \Roberts\HardhatLaravel\HardhatWrapper
 */
class Hardhat extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HardhatWrapper::class;
    }
}
