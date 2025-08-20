<?php

namespace Roberts\HardhatLaravel\Protocols\Evm\Base;

use Roberts\HardhatLaravel\Protocols\Evm\EvmChainAdapter;

class BaseMainnetAdapter implements EvmChainAdapter
{
    public function name(): string
    {
        return 'Base';
    }

    public function network(): string
    {
        return 'base';
    }

    public function chainId(): int
    {
        return 8453;
    }

    public function defaultRpc(): ?string
    {
        return 'https://mainnet.base.org';
    }

    public function toHardhatArgs(): array
    {
        return ['--network', $this->network()];
    }
}
