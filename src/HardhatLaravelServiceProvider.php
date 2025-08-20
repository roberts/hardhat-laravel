<?php

namespace Roberts\HardhatLaravel;

use Illuminate\Support\Facades\Log;
use Roberts\HardhatLaravel\Protocols\Evm\Polygon\PolygonMainnetAdapter;
use Roberts\HardhatLaravel\Commands\HardhatCompileCommand;
use Roberts\HardhatLaravel\Commands\HardhatDoctorCommand;
use Roberts\HardhatLaravel\Commands\HardhatLaravelCommand;
use Roberts\HardhatLaravel\Commands\HardhatRunCommand;
use Roberts\HardhatLaravel\Commands\HardhatTestCommand;
use Roberts\HardhatLaravel\Commands\HardhatUpdateCommand;
use Roberts\HardhatLaravel\Commands\Web3DeployCommand;
use Roberts\HardhatLaravel\Commands\Web3VerifyCommand;
use Roberts\HardhatLaravel\Protocols\Evm\AbstractChain\AbstractMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\ApeChain\ApeChainMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\Arbitrum\ArbitrumOneAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\Base\BaseMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\Ethereum\EthereumMainnetAdapter;
use Roberts\HardhatLaravel\Protocols\Evm\EvmChainRegistry;
use Roberts\HardhatLaravel\Protocols\Evm\Optimism\OptimismMainnetAdapter;
 
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
                Web3DeployCommand::class,
                Web3VerifyCommand::class,
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

        // Model macros for ergonomic API (register at boot time to avoid early Model initialization)
        \Roberts\Web3Laravel\Models\Contract::macro('verifyWithHardhat', function (array $options = []) {
            /** @var \Roberts\Web3Laravel\Models\Contract $this */
            $network = $options['network'] ?? null;
            if (! $network && isset($this->chain_id)) {
                $adapter = app(\Roberts\HardhatLaravel\Protocols\Evm\EvmChainRegistry::class)->forChainId((int) $this->chain_id);
                $network = $adapter?->network();
            }
            if (! $network) {
                throw new \InvalidArgumentException('Network is required for verification.');
            }
            $args = $options['constructor_args'] ?? [];
            $env = $options['env'] ?? [];
            dispatch(new \Roberts\HardhatLaravel\Jobs\VerifyContractJob($this->id, $network, $args, $env));
        });

        \Roberts\Web3Laravel\Models\Wallet::macro('deployArtifact', function (string $artifact, array $constructorArgs = [], array $opts = []) {
            /** @var \Roberts\Web3Laravel\Models\Wallet $this */
            // For now, reuse the CLI via Artisan call to keep logic centralized
            $argsJson = json_encode(array_values($constructorArgs));
            $cmd = [
                'web3:deploy',
                'artifact' => $artifact,
                '--args' => $argsJson,
                '--wallet-id' => (string) $this->id,
            ];
            if (isset($opts['chain_id'])) {
                $cmd['--chain-id'] = (string) $opts['chain_id'];
            }
            if (isset($opts['network'])) {
                $cmd['--network'] = (string) $opts['network'];
            }
            return \Illuminate\Support\Facades\Artisan::call(...$cmd);
        });
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
