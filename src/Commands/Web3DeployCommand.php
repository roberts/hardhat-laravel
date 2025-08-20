<?php

namespace Roberts\HardhatLaravel\Commands;

use Illuminate\Console\Command;
use Roberts\HardhatLaravel\HardhatWrapper;
use Roberts\HardhatLaravel\Protocols\Evm\EvmChainRegistry;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

class Web3DeployCommand extends Command
{
    protected $signature = 'web3:deploy
        {artifact : Contract artifact name (e.g., MyToken)}
        {--args= : Constructor args as JSON array, e.g., ["arg1","arg2"]}
        {--wallet-id= : Signer wallet id}
        {--wallet-address= : Signer wallet address}
        {--chain-id= : Target EVM chain id}
        {--network= : Hardhat network name (e.g., base, sepolia)}
        {--script=scripts/deploy-data.ts : Hardhat script that prints deploy data JSON}
        {--value=0 : Payable value in wei (decimal string)}
    {--auto-verify : Automatically queue verification after confirmation}
        ';

    protected $description = 'Prepare and enqueue an EVM contract deployment transaction using Hardhat-provided deploy data.';

    public function handle(HardhatWrapper $hardhat, EvmChainRegistry $registry): int
    {
        $artifact = (string) $this->argument('artifact');
        $argsJson = $this->option('args') ?? '[]';
        $network = $this->option('network');
        $script = (string) $this->option('script');
        $value = (string) $this->option('value');
    $autoVerify = (bool) $this->option('auto-verify');

        // Quick pre-check: if neither wallet option is provided, fail fast before invoking Hardhat
        if (! $this->option('wallet-id') && ! $this->option('wallet-address')) {
            $this->error('Signer wallet not found. Provide --wallet-id or --wallet-address.');

            return self::FAILURE;
        }

        // Resolve chain id (from option or defaults)
        $chainId = $this->option('chain-id');
        $chainId = $chainId ? (int) $chainId : (int) (config('web3-laravel.default_chain_id'));

        // Build Hardhat args for the helper script (infer network if not provided)
        $hhArgs = [];
        if ($network) {
            $hhArgs[] = '--network';
            $hhArgs[] = $network;
        } else {
            $adapter = $registry->forChainId($chainId);
            if ($adapter) {
                $hhArgs = array_merge($hhArgs, $adapter->toHardhatArgs());
                $network = $adapter->network();
            }
        }
        // Pass artifact and args to the script via cli flags
        $hhArgs[] = '--artifact='.$artifact;
        $hhArgs[] = '--args='.$argsJson;

        $this->info("Fetching deploy data from Hardhat ({$script})...");
        try {
            $out = $hardhat->runScript($script, $hhArgs);
        } catch (\Throwable $e) {
            $this->error('Hardhat script failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $payload = json_decode(trim($out), true);
        if (! is_array($payload) || empty($payload['data'])) {
            $this->error('Invalid deploy JSON. Expected a top-level object with a "data" field.');

            return self::FAILURE;
        }

        $deployData = (string) $payload['data'];
        $abi = $payload['abi'] ?? null;
        $constructorArgs = $payload['constructorArgs'] ?? null;

        // Resolve signer wallet after successful Hardhat call (allows tests to short-circuit before DB)
        $wallet = $this->resolveWallet();
        if (! $wallet) {
            $this->error('Signer wallet not found. Provide --wallet-id or --wallet-address.');

            return self::FAILURE;
        }
        if (! $wallet->protocol->isEvm()) {
            $this->error('Signer wallet must be an EVM wallet.');

            return self::FAILURE;
        }

        // Resolve blockchain id late
        $blockchainId = optional(Blockchain::query()->where('chain_id', $chainId)->first())->id;

        // Create Transaction (to = null for contract creation)
        $tx = Transaction::create([
            'wallet_id' => $wallet->id,
            'blockchain_id' => $blockchainId,
            'from' => $wallet->address,
            'to' => null,
            'value' => $value,
            'data' => $deployData,
            'chain_id' => $chainId,
            'function' => 'deploy_contract',
            'function_params' => [
                'artifact' => $artifact,
                'constructor_args' => $constructorArgs,
                'abi_present' => $abi !== null,
                'network' => $network,
            ],
            'meta' => [
                'artifact' => $artifact,
                'abi' => $abi,
                'constructor_args' => $constructorArgs,
                'bytecode_len' => isset($payload['bytecode']) ? strlen((string) $payload['bytecode']) : null,
                'auto_verify' => $autoVerify,
            ],
        ]);

        $this->info('Enqueued deployment transaction id='.$tx->id.' (status='.$tx->statusValue().').');
        $this->line('The async pipeline will sign, broadcast, and confirm. Listen for TransactionConfirmed to persist the Contract.');

        return self::SUCCESS;
    }

    private function resolveWallet(): ?Wallet
    {
        $id = $this->option('wallet-id');
        $addr = $this->option('wallet-address');
        if ($id) {
            return Wallet::query()->find((int) $id);
        }
        if ($addr) {
            return Wallet::query()->where('address', $addr)->first();
        }

        return null;
    }
}
