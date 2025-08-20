<?php

namespace Roberts\HardhatLaravel\Commands;

use Illuminate\Console\Command;
use Roberts\HardhatLaravel\HardhatWrapper;
use Roberts\HardhatLaravel\Protocols\Evm\EvmChainRegistry;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Contract as Web3Contract;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

class EVMCallCommand extends Command
{
    protected $signature = 'evm:call
        {contract : Contract id or address}
        {function : Function name (or full signature)}
        {--args= : Function args as JSON array, e.g., ["arg1","arg2"]}
        {--signature= : Full function signature to disambiguate (e.g., transfer(address,uint256))}
        {--wallet-id= : Signer wallet id (defaults to contract creator)}
        {--wallet-address= : Signer wallet address (defaults to contract creator)}
        {--chain-id= : Target EVM chain id}
        {--network= : Hardhat network name (e.g., base, sepolia)}
        {--script=scripts/call-data.ts : Hardhat script that prints call data JSON}
        {--value=0 : Payable value in wei (decimal string)}
    ';

    protected $description = 'Prepare and enqueue an EVM contract function call using Hardhat-provided call data.';

    public function handle(HardhatWrapper $hardhat, EvmChainRegistry $registry): int
    {
        $contractInput = (string) $this->argument('contract');
        $function = (string) $this->argument('function');
        $argsJson = $this->option('args') ?? '[]';
        $signature = $this->option('signature');
        $network = $this->option('network');
        $script = (string) $this->option('script');
        $value = (string) $this->option('value');

        $contract = $this->resolveContract($contractInput);
        if (! $contract || ! $contract->address) {
            $this->error('Contract not found. Provide a valid contract id or address.');

            return self::FAILURE;
        }

        $chainId = $this->option('chain-id');
        if ($chainId) {
            $chainId = (int) $chainId;
        } else {
            $chainId = (int) ($contract->blockchain->chain_id ?? config('web3-laravel.default_chain_id'));
        }

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

        $hhArgs[] = '--address='.$contract->address;
        if ($signature) {
            $hhArgs[] = '--signature='.$signature;
        } else {
            $hhArgs[] = '--function='.$function;
        }
        $hhArgs[] = '--args='.$argsJson;

        $this->info("Fetching call data from Hardhat ({$script})...");
        try {
            $out = $hardhat->runScript($script, $hhArgs);
        } catch (\Throwable $e) {
            $this->error('Hardhat script failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $payload = json_decode(trim($out), true);
        if (! is_array($payload) || empty($payload['data'])) {
            $this->error('Invalid call JSON. Expected a top-level object with a "data" field.');

            return self::FAILURE;
        }

        $callData = (string) $payload['data'];

        $wallet = $this->resolveWallet($contract);
        if (! $wallet) {
            $this->error('Signer wallet not found. Provide --wallet-id/--wallet-address or ensure the creator wallet exists.');

            return self::FAILURE;
        }
        if (! $wallet->protocol->isEvm()) {
            $this->error('Signer wallet must be an EVM wallet.');

            return self::FAILURE;
        }

        $blockchainId = optional(Blockchain::query()->where('chain_id', $chainId)->first())->id;

        $tx = Transaction::create([
            'wallet_id' => $wallet->id,
            'blockchain_id' => $blockchainId,
            'from' => $wallet->address,
            'to' => $contract->address,
            'value' => $value,
            'data' => $callData,
            'chain_id' => $chainId,
            'function' => $function,
            'function_params' => [
                'contract_id' => $contract->id ?? null,
                'contract_address' => $contract->address,
                'args' => json_decode($argsJson, true) ?? [],
                'signature' => $signature,
                'network' => $network,
            ],
            'meta' => [
                'via' => 'hardhat',
            ],
        ]);

        $this->info('Enqueued function call transaction id='.$tx->id.' (status='.$tx->statusValue().').');

        return self::SUCCESS;
    }

    private function resolveContract(string $input): ?Web3Contract
    {
        if (preg_match('/^(0x)?[0-9a-fA-F]{40}$/', $input)) {
            return Web3Contract::query()->where('address', strtolower($input))->first();
        }
        if (ctype_digit($input)) {
            return Web3Contract::query()->find((int) $input);
        }

        return null;
    }

    private function resolveWallet(Web3Contract $contract): ?Wallet
    {
        $id = $this->option('wallet-id');
        $addr = $this->option('wallet-address');
        if ($id) {
            return Wallet::query()->find((int) $id);
        }
        if ($addr) {
            return Wallet::query()->where('address', $addr)->first();
        }

        if ($contract->creator) {
            return Wallet::query()->where('address', $contract->creator)->first();
        }

        return null;
    }
}
