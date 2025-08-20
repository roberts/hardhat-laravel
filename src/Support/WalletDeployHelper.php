<?php

namespace Roberts\HardhatLaravel\Support;

use Illuminate\Support\Facades\Artisan;
use Roberts\Web3Laravel\Models\Contract as Web3Contract;
use Roberts\Web3Laravel\Models\Wallet;

/**
 * Helper for deploying contracts via Hardhat using a Wallet context.
 */
class WalletDeployHelper
{
    /**
     * Deploy an artifact via evm:deploy (returns Artisan status code).
     */
    public static function deployArtifact(Wallet $wallet, string $artifact, array $constructorArgs = [], array $opts = []): int
    {
        $argsJson = json_encode(array_values($constructorArgs));
        $command = 'evm:deploy';
        $parameters = [
            'artifact' => $artifact,
            '--args' => $argsJson,
            '--wallet-id' => (string) $wallet->id,
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
    }

    /**
     * Deploy and return the created Transaction id when parseable from Artisan output.
     */
    public static function deployContract(Wallet $wallet, string $artifact, array $constructorArgs = [], array $opts = []): ?int
    {
        $status = self::deployArtifact($wallet, $artifact, $constructorArgs, $opts);

        // Ignore $status here; we only parse the output for an ID.
        $output = Artisan::output();
        if (preg_match('/transaction id=(\d+)/i', $output, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Enqueue a function call on a contract using evm:call.
     * Returns the created Transaction id when parseable from Artisan output; otherwise null.
     */
    public static function callContract(Wallet $wallet, Web3Contract $contract, string $function, array $args = [], array $opts = []): ?int
    {
        $argsJson = json_encode(array_values($args));
        $parameters = [
            'contract' => (string) ($contract->id ?? $contract->address),
            'function' => $function,
            '--args' => $argsJson,
            '--wallet-id' => (string) $wallet->id,
        ];
        if (isset($opts['chain_id'])) {
            $parameters['--chain-id'] = (string) $opts['chain_id'];
        }
        if (isset($opts['network'])) {
            $parameters['--network'] = (string) $opts['network'];
        }
        if (isset($opts['signature'])) {
            $parameters['--signature'] = (string) $opts['signature'];
        }
        if (isset($opts['value'])) {
            $parameters['--value'] = (string) $opts['value'];
        }

        Artisan::call('evm:call', $parameters);
        $output = Artisan::output();
        if (preg_match('/transaction id=(\d+)/i', $output, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
