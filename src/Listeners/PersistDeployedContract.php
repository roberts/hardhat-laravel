<?php

namespace Roberts\HardhatLaravel\Listeners;

use Roberts\HardhatLaravel\Jobs\PopulateAssetRecordsJob;
use Roberts\HardhatLaravel\Protocols\Evm\EvmChainRegistry;
use Roberts\HardhatLaravel\Services\AbiService;
use Roberts\Web3Laravel\Events\TransactionConfirmed;
use Roberts\Web3Laravel\Models\Contract as Web3Contract;

class PersistDeployedContract
{
    public function handle(TransactionConfirmed $event): void
    {
        $tx = $event->transaction;
        // Only handle EVM contract creation tx (to is null and receipt has contractAddress)
        $receipt = (array) data_get($tx->meta, 'receipt', []);
        $address = (string) ($receipt['contractAddress'] ?? '');
        if ($address === '' || ! empty($tx->to)) {
            return;
        }

        $abi = app(AbiService::class)->normalize(data_get($tx->meta, 'abi'));
        $creator = $tx->from;

        $contract = Web3Contract::query()->firstOrCreate(
            ['address' => $address],
            [
                'blockchain_id' => $tx->blockchain_id,
                'creator' => $creator,
                'abi' => $abi,
            ]
        );

        // If ABI exists, queue a background job to detect token standard and create Token/NFT records
        if (is_array($abi) && ! empty($abi)) {
            dispatch(new PopulateAssetRecordsJob($contract->id));
        }

        // Optionally dispatch verification after persist if requested
        $autoVerify = (bool) data_get($tx->meta, 'auto_verify', false);
        if ($autoVerify) {
            $network = data_get($tx->function_params, 'network');
            if (! $network && $tx->chain_id) {
                $adapter = app(EvmChainRegistry::class)->forChainId((int) $tx->chain_id);
                $network = $adapter?->network();
            }
            if ($network) {
                dispatch(new \Roberts\HardhatLaravel\Jobs\VerifyContractJob($contract->id, (string) $network, (array) data_get($tx->meta, 'constructor_args', [])));
            }
        }
    }
}
