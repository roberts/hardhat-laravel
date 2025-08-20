<?php

use Illuminate\Support\Facades\Queue;
use Roberts\HardhatLaravel\Jobs\PopulateAssetRecordsJob;
use Roberts\HardhatLaravel\Tests\TestCase;
use Roberts\Web3Laravel\Events\TransactionConfirmed;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Contract;

it('dispatches PopulateAssetRecordsJob when ABI is present on confirmed deploy', function () {
    /** @var TestCase $this */
    Queue::fake();

    $chain = Blockchain::factory()->create(['chain_id' => 8453]);

    $erc20Abi = [
        ['type' => 'function', 'name' => 'name', 'inputs' => [], 'outputs' => [['type' => 'string']]],
        ['type' => 'function', 'name' => 'symbol', 'inputs' => [], 'outputs' => [['type' => 'string']]],
        ['type' => 'function', 'name' => 'decimals', 'inputs' => [], 'outputs' => [['type' => 'uint8']]],
        ['type' => 'function', 'name' => 'totalSupply', 'inputs' => [], 'outputs' => [['type' => 'uint256']]],
        ['type' => 'function', 'name' => 'balanceOf', 'inputs' => [['type' => 'address']], 'outputs' => [['type' => 'uint256']]],
    ];

    $tx = Transaction::factory()->create([
        'blockchain_id' => $chain->id,
        'to' => null,
        'chain_id' => 8453,
        'meta' => [
            'abi' => $erc20Abi,
            'receipt' => [
                'contractAddress' => '0x000000000000000000000000000000000000beef',
            ],
        ],
    ]);

    event(new TransactionConfirmed($tx));

    // Contract should be persisted
    $contract = Contract::query()->where('address', '0x000000000000000000000000000000000000beef')->first();
    expect($contract)->not->toBeNull();

    // PopulateAssetRecordsJob should be queued
    Queue::assertPushed(PopulateAssetRecordsJob::class, function (PopulateAssetRecordsJob $job) use ($contract) {
        return $job->contractId === $contract->id;
    });
});
