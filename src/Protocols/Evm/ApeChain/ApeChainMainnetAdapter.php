<?php

namespace Roberts\HardhatLaravel\Protocols\Evm\ApeChain;

use Roberts\HardhatLaravel\Protocols\Evm\EvmChainAdapter;

class ApeChainMainnetAdapter implements EvmChainAdapter
{
    public function name(): string
    {
        return 'ApeChain';
    }

    public function network(): string
    {
        return 'apechain';
    }

    public function chainId(): int
    {
        return 33139;
    }

    public function defaultRpc(): ?string
    {
        return 'https://rpc.apechain.com';
    }

    public function toHardhatArgs(): array
    {
        return ['--network', $this->network()];
    }
}
