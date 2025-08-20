<?php

use Illuminate\Support\Facades\Artisan;
use Roberts\HardhatLaravel\Tests\TestCase;

it('fails gracefully when no wallet is provided', function () {
    /** @var TestCase $this */
    $exit = Artisan::call('web3:deploy', [
        'artifact' => 'MyToken',
        '--args' => '[]',
        '--chain-id' => 8453,
    ]);

    expect($exit)->toBe(1); // FAILURE
});
