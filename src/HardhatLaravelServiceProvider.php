<?php

namespace Roberts\HardhatLaravel;

use Roberts\HardhatLaravel\Commands\HardhatCompileCommand;
use Roberts\HardhatLaravel\Commands\HardhatLaravelCommand;
use Roberts\HardhatLaravel\Commands\HardhatRunCommand;
use Roberts\HardhatLaravel\Commands\HardhatTestCommand;
use Roberts\HardhatLaravel\Commands\HardhatUpdateCommand;
use Roberts\HardhatLaravel\Commands\Web3DeployCommand;
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
    }
}
