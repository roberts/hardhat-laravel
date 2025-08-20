<?php

use Illuminate\Support\Facades\Queue;
use Roberts\HardhatLaravel\Jobs\VerifyContractJob;
use Roberts\HardhatLaravel\Tests\TestCase;
use Roberts\Web3Laravel\Events\TransactionConfirmed;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

it('queues verification when auto-verify flag is set on deploy meta', function () {
    /** @var TestCase $this */
    Queue::fake();

    // Create blockchain and wallet fixtures
    $chain = Blockchain::factory()->create(['chain_id' => 8453]);
    $wallet = Wallet::factory()->create();

    // Create a transaction that represents a confirmed contract creation
    $tx = Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'blockchain_id' => $chain->id,
        'from' => $wallet->address,
        'to' => null,
        'chain_id' => 8453,
        'function' => 'deploy_contract',
        'function_params' => [
            'network' => 'base',
        ],
        'meta' => [
            'auto_verify' => true,
            'constructor_args' => ['Alice', 'ALC'],
            'receipt' => [
                'contractAddress' => '0x000000000000000000000000000000000000beef',
            ],
        ],
    ]);

    // Fire the confirmed event
    event(new TransactionConfirmed($tx));

    // The listener should persist a Contract and dispatch a VerifyContractJob
    Queue::assertPushed(VerifyContractJob::class, fn (VerifyContractJob $job) => $job->network === 'base');
});
