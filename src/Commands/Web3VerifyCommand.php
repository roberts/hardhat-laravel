<?php

namespace Roberts\HardhatLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Roberts\HardhatLaravel\Jobs\VerifyContractJob;
use Roberts\HardhatLaravel\Protocols\Evm\EvmChainRegistry;
use Roberts\HardhatLaravel\Services\VerifyService;
use Roberts\Web3Laravel\Models\Contract;

class Web3VerifyCommand extends Command
{
    protected $signature = 'web3:verify
        {address : Deployed contract address}
        {--network= : Hardhat network name (e.g., base, sepolia)}
        {--chain-id= : EVM chain id to infer network}
        {--args= : Constructor args as JSON array}
        {--contract-id= : Contract id to update meta}
        {--queue : Queue a background verification job instead of running synchronously}
    ';

    protected $description = 'Verify a deployed contract using Hardhat verify, optionally queuing a background job.';

    public function handle(VerifyService $verify, EvmChainRegistry $registry): int
    {
        $address = (string) $this->argument('address');
        $network = $this->option('network');
        $chainId = $this->option('chain-id');
        $argsJson = $this->option('args') ?? '[]';
        $contractId = $this->option('contract-id');
        $queue = (bool) $this->option('queue');

        if (! $network) {
            if ($chainId) {
                $adapter = $registry->forChainId((int) $chainId);
                $network = $adapter?->network();
            } elseif ($contractId) {
                $c = Contract::query()->with('blockchain')->find((int) $contractId);
                if ($c && $c->blockchain) {
                    $adapter = $registry->forChainId((int) $c->blockchain->chain_id);
                    $network = $adapter?->network();
                }
            }
        }

        if (! $network) {
            $this->error('Network is required. Provide --network or --chain-id or --contract-id that can infer it.');

            return self::FAILURE;
        }

        $constructorArgs = [];
        try {
            $decoded = json_decode($argsJson, true);
            if (is_array($decoded)) {
                $constructorArgs = array_values($decoded);
            }
        } catch (\Throwable $e) {
            // ignore, use empty args
        }

        if ($queue) {
            if (! $contractId) {
                $this->error('--queue requires --contract-id to update the correct record.');

                return self::FAILURE;
            }
            Queue::push(new VerifyContractJob((int) $contractId, (string) $network, $constructorArgs));
            $this->info('Queued verification job for contract id='.$contractId.' on network='.$network.'.');

            return self::SUCCESS;
        }

        try {
            $out = $verify->verify($address, (string) $network, $constructorArgs);
            $this->line(trim($out));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Verification failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
