<?php

namespace Roberts\HardhatLaravel\Protocols\Evm\Optimism;

use Roberts\HardhatLaravel\Protocols\Evm\EvmChainAdapter;

class OptimismMainnetAdapter implements EvmChainAdapter
{
    public function name(): string
    {
        return 'Optimism';
    }

    public function network(): string
    {
        return 'optimism';
    }

    public function chainId(): int
    {
        return 10;
    }

    public function defaultRpc(): ?string
    {
        return 'https://mainnet.optimism.io';
    }

    public function toHardhatArgs(): array
    {
        return ['--network', $this->network()];
    }
}
