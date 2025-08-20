<?php

namespace Roberts\HardhatLaravel\Services;

use Roberts\Web3Laravel\Models\Contract as Web3Contract;
use Roberts\Web3Laravel\Models\Transaction;

class AbiService
{
    /**
     * Normalize an ABI provided as JSON string or array to a PHP array.
     */
    public function normalize(array|string|null $abi): ?array
    {
        if (is_array($abi)) {
            return $abi;
        }
        if (is_string($abi) && $abi !== '') {
            $decoded = json_decode($abi, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * Persist ABI into the database on the created Contract record using data from a Transaction.
     * This does not write any files and mirrors the behavior used in the event listener.
     * Returns the Contract if one was created or found, otherwise null (e.g., not a creation tx).
     */
    public function persistFromTransaction(Transaction $tx): ?Web3Contract
    {
        $receipt = (array) data_get($tx->meta, 'receipt', []);
        $address = (string) ($receipt['contractAddress'] ?? '');
        if ($address === '' || ! empty($tx->to)) {
            return null; // Not a contract creation transaction
        }

        $abi = $this->normalize(data_get($tx->meta, 'abi'));
        $creator = $tx->from;

        return Web3Contract::query()->firstOrCreate(
            ['address' => $address],
            [
                'blockchain_id' => $tx->blockchain_id,
                'creator' => $creator,
                'abi' => $abi,
            ]
        );
    }
}
