<?php

namespace Roberts\HardhatLaravel;

use Illuminate\Support\Facades\Log;
use Roberts\HardhatLaravel\Commands\EVMCallCommand;
use Roberts\HardhatLaravel\Commands\EVMDeployCommand;
use Roberts\HardhatLaravel\Commands\EVMVerifyCommand;
use Roberts\HardhatLaravel\Commands\HardhatCompileCommand;
use Roberts\HardhatLaravel\Commands\HardhatDoctorCommand;
use Roberts\HardhatLaravel\Commands\HardhatRunCommand;
use Roberts\HardhatLaravel\Commands\HardhatTestCommand;
use Roberts\HardhatLaravel\Commands\HardhatUpdateCommand;
use Roberts\HardhatLaravel\Protocols\Evm\AbstractChain\AbstractMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\ApeChain\ApeChainMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\Arbitrum\ArbitrumOneAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\Base\BaseMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\Ethereum\EthereumMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\EvmChainRegistry;
use Roberts\HardhatLaravel\Protocols\Evm\Optimism\OptimismMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\Polygon\PolygonMainnetAdapter;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class HardhatLaravelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('hardhat-laravel')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_hardhat_laravel_table')
            ->hasCommands([
                HardhatCompileCommand::class,
                HardhatRunCommand::class,
                HardhatTestCommand::class,
                HardhatUpdateCommand::class,
                HardhatDoctorCommand::class,
                EVMDeployCommand::class,
                EVMCallCommand::class,
                EVMVerifyCommand::class,
            ]);
    }

    public function bootingPackage(): void
    {
        // Startup diagnostics: warn if the expected Hardhat directory/config is missing
        $path = base_path('..'.DIRECTORY_SEPARATOR.'blockchain');
        if (! is_dir($path)) {
            Log::warning('[hardhat-laravel] Expected Hardhat project directory not found: '.$path.' — run "php artisan hardhat:doctor" to diagnose.');
        } else {
            $cfgJs = $path.DIRECTORY_SEPARATOR.'hardhat.config.js';
            $cfgTs = $path.DIRECTORY_SEPARATOR.'hardhat.config.ts';
            if (! file_exists($cfgJs) && ! file_exists($cfgTs)) {
                Log::warning('[hardhat-laravel] Hardhat config not found in '.$path.' (missing hardhat.config.js/ts) — run "php artisan hardhat:doctor" to diagnose.');
            }
        }

        // Listen for confirmed transactions to persist deployed contracts automatically
        \Illuminate\Support\Facades\Event::listen(
            \Roberts\Web3Laravel\Events\TransactionConfirmed::class,
            \Roberts\HardhatLaravel\Listeners\PersistDeployedContract::class
        );

        // Note: Eloquent Model doesn't include Macroable; macros are provided via helper class instead.
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(HardhatWrapper::class, function ($app) {
            // Fixed path: expect Hardhat in a sibling ../blockchain directory
            return new HardhatWrapper(base_path('../blockchain'));
        });

        // Register the EVM chain registry and built-in adapters
        $this->app->singleton(EvmChainRegistry::class, function ($app) {
            $registry = new EvmChainRegistry;
            foreach ([
                new EthereumMainnetAdapter,
                new BaseMainnetAdapter,
                new PolygonMainnetAdapter,
                new ArbitrumOneAdapter,
                new OptimismMainnetAdapter,
                new AbstractMainnetAdapter,
                new ApeChainMainnetAdapter,
            ] as $adapter) {
                $registry->register($adapter);
            }

            return $registry;
        });
    }
}
