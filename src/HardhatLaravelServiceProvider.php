<?php

namespace Roberts\HardhatLaravel;

use Roberts\HardhatLaravel\Commands\HardhatCompileCommand;
use Roberts\HardhatLaravel\Commands\HardhatLaravelCommand;
use Roberts\HardhatLaravel\Commands\HardhatRunCommand;
use Roberts\HardhatLaravel\Commands\HardhatTestCommand;
use Roberts\HardhatLaravel\Commands\HardhatUpdateCommand;
use Roberts\HardhatLaravel\Commands\Web3DeployCommand;
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
                HardhatLaravelCommand::class,
                HardhatCompileCommand::class,
                HardhatRunCommand::class,
                HardhatTestCommand::class,
                HardhatUpdateCommand::class,
                Web3DeployCommand::class,
            ]);
    }

    public function bootingPackage(): void
    {
        // Listen for confirmed transactions to persist deployed contracts automatically
        \Illuminate\Support\Facades\Event::listen(
            \Roberts\Web3Laravel\Events\TransactionConfirmed::class,
            \Roberts\HardhatLaravel\Listeners\PersistDeployedContract::class
        );
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(HardhatWrapper::class, function ($app) {
            $path = config('hardhat-laravel.project_path', base_path('blockchain'));

            return new HardhatWrapper($path);
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
