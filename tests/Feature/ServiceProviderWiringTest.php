<?php

use Roberts\HardhatLaravel\Protocols\Evm\EvmChainRegistry;
use Roberts\HardhatLaravel\Tests\TestCase;

it('binds EvmChainRegistry with built-in adapters', function () {
    /** @var TestCase $this */
    $app = $this->app;
    $registry = $app->make(EvmChainRegistry::class);

    // Expect a few well-known chainIds to be present
    expect($registry->forChainId(1))->not->toBeNull();      // Ethereum
    expect($registry->forChainId(8453))->not->toBeNull();   // Base
    expect($registry->forChainId(137))->not->toBeNull();    // Polygon
});
