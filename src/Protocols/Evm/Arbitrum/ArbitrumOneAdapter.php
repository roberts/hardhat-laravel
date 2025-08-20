<?php

namespace Roberts\HardhatLaravel\Protocols\Evm\Arbitrum;

use Roberts\HardhatLaravel\Protocols\Evm\EvmChainAdapter;

class ArbitrumOneAdapter implements EvmChainAdapter
{
    public function name(): string { return 'Arbitrum One'; }
    public function network(): string { return 'arbitrum'; }
    public function chainId(): int { return 42161; }
    public function defaultRpc(): ?string { return 'https://arb1.arbitrum.io/rpc'; }
    public function toHardhatArgs(): array { return ['--network', $this->network()]; }
}
