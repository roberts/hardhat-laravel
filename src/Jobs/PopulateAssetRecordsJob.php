<?php

namespace Roberts\HardhatLaravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Roberts\HardhatLaravel\Services\TokenDetectionService;
use Roberts\Web3Laravel\Enums\TokenType;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\NftCollection;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Services\ContractCaller;

class PopulateAssetRecordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $contractId)
    {
    }

    public function handle(ContractCaller $caller, TokenDetectionService $detector): void
    {
        $contract = Contract::query()->find($this->contractId);
        if (! $contract || empty($contract->abi)) {
            return;
        }

        $type = $detector->detect($contract->abi);
        if (! $type) {
            return;
        }

        switch ($type) {
            case TokenType::ERC20:
                $this->ensureErc20($contract, $caller);
                return;
            case TokenType::ERC721:
            case TokenType::ERC1155:
                $this->ensureNftCollection($contract, $caller, $type);
                return;
            default:
                return;
        }
    }

    private function ensureErc20(Contract $contract, ContractCaller $caller): void
    {
        // Try to read name/symbol/decimals/totalSupply via calls; fall back to sensible defaults
        $name = $this->tryCallSingle($caller, $contract, 'name') ?? 'Token';
        $symbol = $this->tryCallSingle($caller, $contract, 'symbol') ?? 'TKN';
        $decimals = (int) ($this->tryCallSingle($caller, $contract, 'decimals') ?? 18);
        $totalSupply = (string) ($this->tryCallSingle($caller, $contract, 'totalSupply') ?? '0');

        Token::query()->firstOrCreate(
            ['contract_id' => $contract->id],
            [
                'name' => (string) $name,
                'symbol' => (string) $symbol,
                'decimals' => $decimals,
                'total_supply' => $totalSupply,
            ]
        );
    }

    private function ensureNftCollection(Contract $contract, ContractCaller $caller, TokenType $type): void
    {
        // ERC-721 typically has name/symbol. ERC-1155 usually lacks them; use placeholders.
        $name = $this->tryCallSingle($caller, $contract, 'name');
        $symbol = $this->tryCallSingle($caller, $contract, 'symbol');

        if (! $name) {
            $name = $type === TokenType::ERC1155 ? 'ERC1155 Collection' : 'NFT Collection';
        }
        if (! $symbol) {
            $symbol = $type === TokenType::ERC1155 ? 'ERC1155' : 'NFT';
        }

        NftCollection::query()->firstOrCreate(
            ['contract_id' => $contract->id],
            [
                'name' => (string) $name,
                'symbol' => (string) $symbol,
                'standard' => $type->value,
            ]
        );
    }

    private function tryCallSingle(ContractCaller $caller, Contract $contract, string $function)
    {
        try {
            $res = $caller->call($contract->abi, (string) $contract->address, $function, []);
            return $res[0] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
