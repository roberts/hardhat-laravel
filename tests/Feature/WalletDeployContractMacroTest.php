<?php

use Illuminate\Support\Facades\Artisan;
use Roberts\HardhatLaravel\Support\WalletDeployHelper;
use Roberts\Web3Laravel\Models\Wallet;

it('deployContract returns transaction id parsed from output', function () {
    /** @var Wallet $wallet */
    $wallet = Wallet::factory()->create();

    $original = Artisan::getFacadeRoot();
    $fake = new class('Enqueued deployment transaction id=123 (status=pending).', 0)
    {
        public function __construct(public string $out, public int $status) {}

        public function call($command, array $parameters = [])
        {
            return $this->status;
        }

        public function output()
        {
            return $this->out;
        }

        public function __call($name, $args)
        {
            return null;
        }
    };
    Artisan::swap($fake);

    try {
        $txId = WalletDeployHelper::deployContract($wallet, 'MyArtifact', ['a', 'b'], [
            'chain_id' => 8453,
            'network' => 'base',
            'auto_verify' => true,
        ]);
    } finally {
        Artisan::swap($original);
    }

    expect($txId)->toBe(123);
});

it('deployContract returns null when id cannot be parsed', function () {
    /** @var Wallet $wallet */
    $wallet = Wallet::factory()->create();

    $original = Artisan::getFacadeRoot();
    $fake = new class('No id here', 0)
    {
        public function __construct(public string $out, public int $status) {}

        public function call($command, array $parameters = [])
        {
            return $this->status;
        }

        public function output()
        {
            return $this->out;
        }

        public function __call($name, $args)
        {
            return null;
        }
    };
    Artisan::swap($fake);

    try {
        $txId = WalletDeployHelper::deployContract($wallet, 'MyArtifact');
    } finally {
        Artisan::swap($original);
    }

    expect($txId)->toBeNull();
});

it('deployArtifact returns artisan status code', function () {
    /** @var Wallet $wallet */
    $wallet = Wallet::factory()->create();

    $original = Artisan::getFacadeRoot();
    $fake = new class('ignored', 0)
    {
        public function __construct(public string $out, public int $status) {}

        public function call($command, array $parameters = [])
        {
            return $this->status;
        }

        public function output()
        {
            return $this->out;
        }

        public function __call($name, $args)
        {
            return null;
        }
    };
    Artisan::swap($fake);

    try {
        $status = WalletDeployHelper::deployArtifact($wallet, 'MyArtifact');
    } finally {
        Artisan::swap($original);
    }

    expect($status)->toBe(0);
});
