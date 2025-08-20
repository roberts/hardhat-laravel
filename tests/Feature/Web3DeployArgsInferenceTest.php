<?php

use Illuminate\Support\Facades\Artisan;
use Roberts\HardhatLaravel\HardhatWrapper;
use Roberts\HardhatLaravel\Tests\TestCase;

class CapturingHardhatWrapper extends HardhatWrapper
{
    public array $capturedArgs = [];

    public function __construct()
    {
        parent::__construct(base_path('blockchain'));
    }

    public function runScript(string $script, array $args = [], array $env = []): string
    {
        $this->capturedArgs = $args;
        throw new RuntimeException('stop before db');
    }
}

it('infers --network from chain-id when not provided', function () {
    /** @var TestCase $this */
    $fake = new CapturingHardhatWrapper;
    $this->app->instance(HardhatWrapper::class, $fake);

    // No DB mocks needed; our CapturingHardhatWrapper throws before any model resolution.

    $exit = Artisan::call('web3:deploy', [
        'artifact' => 'MyToken',
        '--args' => '[]',
        '--chain-id' => 8453, // Base
        '--wallet-id' => 1,
    ]);

    expect($exit)->toBe(1) // command fails due to our fake throwing
        ->and($fake->capturedArgs)->toContain('--network', 'base', '--artifact=MyToken', '--args=[]');
});
