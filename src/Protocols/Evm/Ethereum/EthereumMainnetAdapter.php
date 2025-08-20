<?php

namespace Roberts\HardhatLaravel\Protocols\Evm\Ethereum;

use Roberts\HardhatLaravel\Protocols\Evm\EvmChainAdapter;

class EthereumMainnetAdapter implements EvmChainAdapter
{
    public function name(): string
    {
        return 'Ethereum';
    }

    public function network(): string
    {
        return 'mainnet';
    }

    public function chainId(): int
    {
        return 1;
    }

    public function defaultRpc(): ?string
    {
        return 'https://rpc.ankr.com/eth';
    }

    public function toHardhatArgs(): array
    {
        return ['--network', $this->network()];
    }
}
