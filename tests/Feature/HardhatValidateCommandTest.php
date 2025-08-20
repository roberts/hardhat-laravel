<?php

use Illuminate\Support\Facades\Artisan;
use Roberts\HardhatLaravel\HardhatWrapper;
use Roberts\HardhatLaravel\Tests\TestCase;

class MockHardhatWrapper extends HardhatWrapper
{
    public function __construct(private string $mockPath = '/mock/blockchain')
    {
        parent::__construct($mockPath);
    }

    public function getProjectPath(): string
    {
        return $this->mockPath;
    }

    public function compile(): string
    {
        return 'Compiled successfully';
    }
}

it('validates hardhat project setup and reports missing directory', function () {
    /** @var TestCase $this */
    
    // Mock a wrapper with non-existent path
    $mockWrapper = new MockHardhatWrapper('/non/existent/path');
    $this->app->instance(HardhatWrapper::class, $mockWrapper);

    $exitCode = Artisan::call('hardhat:validate');
    $output = Artisan::output();

    expect($exitCode)->toBe(1) // FAILURE
        ->and($output)->toContain('Hardhat project directory not found')
        ->and($output)->toContain('/non/existent/path');
});

it('validates hardhat project setup successfully when directory exists', function () {
    /** @var TestCase $this */
    
    // Create a temporary directory structure for testing
    $tempDir = sys_get_temp_dir().'/hardhat-test-'.uniqid();
    mkdir($tempDir, 0755, true);
    
    // Create basic structure
    file_put_contents($tempDir.'/package.json', json_encode([
        'devDependencies' => ['hardhat' => '^2.0.0']
    ]));
    file_put_contents($tempDir.'/hardhat.config.js', 'module.exports = {};');
    mkdir($tempDir.'/contracts');
    mkdir($tempDir.'/scripts');
    mkdir($tempDir.'/node_modules');

    $mockWrapper = new MockHardhatWrapper($tempDir);
    $this->app->instance(HardhatWrapper::class, $mockWrapper);

    $exitCode = Artisan::call('hardhat:validate');
    $output = Artisan::output();

    expect($exitCode)->toBe(0) // SUCCESS
        ->and($output)->toContain('package.json exists')
        ->and($output)->toContain('Hardhat config found')
        ->and($output)->toContain('contracts/ directory exists')
        ->and($output)->toContain('scripts/ directory exists')
        ->and($output)->toContain('node_modules/ directory exists')
        ->and($output)->toContain('Hardhat compilation successful');

    // Cleanup
    exec("rm -rf {$tempDir}");
});

it('shows detailed output with verbose flag', function () {
    /** @var TestCase $this */
    
    $mockWrapper = new MockHardhatWrapper('/non/existent/path');
    $this->app->instance(HardhatWrapper::class, $mockWrapper);

    $exitCode = Artisan::call('hardhat:validate', ['--verbose' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('🔍 Validating Hardhat project setup');
});
