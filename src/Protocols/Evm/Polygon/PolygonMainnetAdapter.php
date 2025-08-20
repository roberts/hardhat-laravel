<?php

namespace Roberts\HardhatLaravel\Protocols\Evm\Polygon;

use Roberts\HardhatLaravel\Protocols\Evm\EvmChainAdapter;

class PolygonMainnetAdapter implements EvmChainAdapter
{
    public function name(): string { return 'Polygon'; }
    public function network(): string { return 'polygon'; }
    public function chainId(): int { return 137; }
    public function defaultRpc(): ?string { return 'https://polygon-rpc.com'; }
    public function toHardhatArgs(): array { return ['--network', $this->network()]; }
}
