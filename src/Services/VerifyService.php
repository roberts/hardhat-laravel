<?php

namespace Roberts\HardhatLaravel\Services;

use Roberts\HardhatLaravel\HardhatWrapper;

class VerifyService
{
    public function __construct(private HardhatWrapper $hardhat)
    {
    }

    /**
     * Run hardhat verify for a deployed contract.
     * @param string $address 0x-prefixed address
     * @param string $network Hardhat network name
     * @param array<int,string> $constructorArgs Optional constructor args as strings
     * @param array<string,string> $env Optional environment variables
     * @return string Output from hardhat
     */
    public function verify(string $address, string $network, array $constructorArgs = [], array $env = []): string
    {
        $args = ['--network', $network, $address];
        foreach ($constructorArgs as $arg) {
            $args[] = (string) $arg;
        }

        return $this->hardhat->runCommand('verify', $args, $env);
    }
}
