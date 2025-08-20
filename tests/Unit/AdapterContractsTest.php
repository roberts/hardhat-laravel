<?php

use Roberts\HardhatLaravel\Protocols\Evm\Base\BaseMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\EvmChainAdapter;

it('adapters implement the contract correctly', function () {
    $adapter = new BaseMainnetAdapter;
    expect($adapter)->toBeInstanceOf(EvmChainAdapter::class)
        ->and($adapter->name())->toBe('Base')
        ->and($adapter->network())->toBe('base')
        ->and($adapter->chainId())->toBe(8453)
        ->and($adapter->defaultRpc())->not->toBeNull()
        ->and($adapter->toHardhatArgs())->toBe(['--network', 'base']);
});
