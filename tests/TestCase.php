<?php

namespace Roberts\HardhatLaravel\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Roberts\HardhatLaravel\HardhatLaravelServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if (str_starts_with($modelName, 'Roberts\\Web3Laravel\\Models\\')) {
                return 'Roberts\\Web3Laravel\\Database\\Factories\\'.class_basename($modelName).'Factory';
            }

            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

    // No-op: macros are exposed via helper class now.
    }

    protected function getPackageProviders($app)
    {
        return [
            HardhatLaravelServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        // Load web3-laravel migrations so its models/factories work in tests
        $migs = base_path('vendor/roberts/web3-laravel/database/migrations');
        if (is_dir($migs)) {
            foreach (\Illuminate\Support\Facades\File::allFiles($migs) as $migration) {
                (include $migration->getRealPath())->up();
            }
        }
    }
}
