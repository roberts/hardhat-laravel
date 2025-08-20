<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Roberts\HardhatLaravel\Tests\TestCase;

it('prints diagnostics without throwing', function () {
    /** @var TestCase $this */
    // Fake processes to avoid hitting the real system
    Process::fake([
        'node --version' => Process::result(0, "v20.0.0\n"),
        'npm --version' => Process::result(0, "10.0.0\n"),
        // Assume hardhat is not installed in tests; return failure gracefully
        '*' => Process::result(1, ''),
    ]);

    $exit = Artisan::call('hardhat:doctor');

    expect($exit)->toBe(0);
});
