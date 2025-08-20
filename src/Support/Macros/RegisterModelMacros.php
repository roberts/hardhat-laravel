<?php

namespace Roberts\HardhatLaravel\Support\Macros;

use Illuminate\Support\Facades\Artisan;
use Roberts\HardhatLaravel\Jobs\VerifyContractJob;
use Roberts\HardhatLaravel\Protocols\Evm\EvmChainRegistry;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Wallet;

class RegisterModelMacros
{
    public static function register(): void
    {
        // Contract verification helper: infers network when possible and dispatches a job
        Contract::macro('verifyWithHardhat', function (array $options = []) {
            /** @var \Roberts\Web3Laravel\Models\Contract $this */
            $network = $options['network'] ?? null;
            if (! $network && isset($this->chain_id)) {
                $adapter = app(EvmChainRegistry::class)->forChainId((int) $this->chain_id);
                $network = $adapter?->network();
            }
            if (! $network) {
                throw new \InvalidArgumentException('Network is required for verification.');
            }
            $args = $options['constructor_args'] ?? [];
            $env = $options['env'] ?? [];
            dispatch(new VerifyContractJob($this->id, $network, $args, $env));
        });

        // Deploy an artifact via web3:deploy (returns Artisan status code)
        Wallet::macro('deployArtifact', function (string $artifact, array $constructorArgs = [], array $opts = []) {
            /** @var \Roberts\Web3Laravel\Models\Wallet $this */
            $argsJson = json_encode(array_values($constructorArgs));
            $command = 'web3:deploy';
            $parameters = [
                'artifact' => $artifact,
                '--args' => $argsJson,
                '--wallet-id' => (string) $this->id,
            ];
            if (isset($opts['chain_id'])) {
                $parameters['--chain-id'] = (string) $opts['chain_id'];
            }
            if (isset($opts['network'])) {
                $parameters['--network'] = (string) $opts['network'];
            }
            if (! empty($opts['auto_verify'])) {
                $parameters['--auto-verify'] = true;
            }

            return Artisan::call($command, $parameters);
        });

        // Deploy and return the created Transaction id when parseable
        Wallet::macro('deployContract', function (string $artifact, array $constructorArgs = [], array $opts = []): ?int {
            /** @var \Roberts\Web3Laravel\Models\Wallet $this */
            $argsJson = json_encode(array_values($constructorArgs));
            $command = 'web3:deploy';
            $parameters = [
                'artifact' => $artifact,
                '--args' => $argsJson,
                '--wallet-id' => (string) $this->id,
            ];
            if (isset($opts['chain_id'])) {
                $parameters['--chain-id'] = (string) $opts['chain_id'];
            }
            if (isset($opts['network'])) {
                $parameters['--network'] = (string) $opts['network'];
            }
            if (! empty($opts['auto_verify'])) {
                $parameters['--auto-verify'] = true;
            }

            Artisan::call($command, $parameters);
            $output = Artisan::output();
            if (preg_match('/transaction id=(\d+)/i', $output, $m)) {
                return (int) $m[1];
            }

            return null;
        });
    }
}
