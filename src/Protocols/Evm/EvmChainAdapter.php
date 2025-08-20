<?php

namespace Roberts\HardhatLaravel\Protocols\Evm;

interface EvmChainAdapter
{
    public function name(): string;           // Human-readable chain name

    public function network(): string;        // Hardhat network name

    public function chainId(): int;           // EVM chain id

    public function defaultRpc(): ?string;    // Optional default RPC

    /** Return common Hardhat CLI args for this network (e.g., ['--network','base']). */
    public function toHardhatArgs(): array;
}
