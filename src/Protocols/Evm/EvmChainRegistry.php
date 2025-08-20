<?php

namespace Roberts\HardhatLaravel\Protocols\Evm;

class EvmChainRegistry
{
    /** @var array<int,EvmChainAdapter> */
    protected array $byId = [];

    /** @var array<string,EvmChainAdapter> */
    protected array $byNetwork = [];

    public function register(EvmChainAdapter $adapter): void
    {
        $this->byId[$adapter->chainId()] = $adapter;
        $this->byNetwork[strtolower($adapter->network())] = $adapter;
    }

    public function forChainId(int $chainId): ?EvmChainAdapter
    {
        return $this->byId[$chainId] ?? null;
    }

    public function forNetwork(string $network): ?EvmChainAdapter
    {
        return $this->byNetwork[strtolower($network)] ?? null;
    }

    /** @return array<int,EvmChainAdapter> */
    public function all(): array
    {
        return $this->byId;
    }
}
