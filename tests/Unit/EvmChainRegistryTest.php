<?php

use Roberts\HardhatLaravel\Protocols\Evm\Base\BaseMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\Ethereum\EthereumMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\EvmChainRegistry;

it('registers and resolves adapters by chainId and network', function () {
    $registry = new EvmChainRegistry;
    $base = new BaseMainnetAdapter;
    $eth = new EthereumMainnetAdapter;

    $registry->register($base);
    $registry->register($eth);

    $byId = $registry->forChainId(8453);
    expect($byId)->not->toBeNull()
        ->and($byId->network())->toBe('base')
        ->and($byId->chainId())->toBe(8453);

    $byNet = $registry->forNetwork('MAINNET'); // case-insensitive
    expect($byNet)->not->toBeNull()
        ->and($byNet->chainId())->toBe(1);
});
