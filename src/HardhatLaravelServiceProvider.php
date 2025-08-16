<?php

namespace Roberts\HardhatLaravel;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Roberts\HardhatLaravel\Commands\HardhatLaravelCommand;

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
            ->hasCommand(HardhatLaravelCommand::class);
    }
}
