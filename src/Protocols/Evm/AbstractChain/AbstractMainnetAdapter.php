<?php

namespace Roberts\HardhatLaravel\Protocols\Evm\AbstractChain;

use Roberts\HardhatLaravel\Protocols\Evm\EvmChainAdapter;

class AbstractMainnetAdapter implements EvmChainAdapter
{
    public function name(): string
    {
        return 'Abstract';
    }

    public function network(): string
    {
        return 'abstract';
    }

    public function chainId(): int
    {
        return 2741;
    }

    public function defaultRpc(): ?string
    {
        return 'https://api.mainnet.abs.xyz';
    }

    public function toHardhatArgs(): array
    {
        return ['--network', $this->network()];
    }
}
