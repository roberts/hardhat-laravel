<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Roberts\HardhatLaravel\Jobs\VerifyContractJob;
use Roberts\HardhatLaravel\Tests\TestCase;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Contract;

it('infers network from chain id and runs sync verify', function () {
    /** @var TestCase $this */
    Process::fake([
        '*' => Process::result(0, "Verified
"),
    ]);

    $chain = Blockchain::factory()->create(['chain_id' => 8453]);
    $c = Contract::factory()->create([
        'address' => '0x000000000000000000000000000000000000beef',
        'blockchain_id' => $chain->id,
    ]);

    $exit = Artisan::call('web3:verify', [
        'address' => $c->address,
        '--chain-id' => 8453,
        '--contract-id' => $c->id,
    ]);

    expect($exit)->toBe(0);
});

it('queues a verify job when --queue is used', function () {
    /** @var TestCase $this */
    Queue::fake();

    $chain = Blockchain::factory()->create(['chain_id' => 8453]);
    $c = Contract::factory()->create([
        'address' => '0x000000000000000000000000000000000000beef',
        'blockchain_id' => $chain->id,
    ]);

    $exit = Artisan::call('web3:verify', [
        'address' => $c->address,
        '--chain-id' => 8453,
        '--contract-id' => $c->id,
        '--queue' => true,
    ]);

    expect($exit)->toBe(0);
    Queue::assertPushed(VerifyContractJob::class);
});
