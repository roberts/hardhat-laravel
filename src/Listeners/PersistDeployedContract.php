<?php

namespace Roberts\HardhatLaravel\Listeners;

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

        $abi = data_get($tx->meta, 'abi');
        $creator = $tx->from;

        Web3Contract::query()->firstOrCreate(
            ['address' => $address],
            [
                'blockchain_id' => $tx->blockchain_id,
                'creator' => $creator,
                'abi' => $abi,
            ]
        );
    }
}
