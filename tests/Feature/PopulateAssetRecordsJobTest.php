<?php

use Mockery as m;
use Roberts\HardhatLaravel\Jobs\PopulateAssetRecordsJob;
use Roberts\HardhatLaravel\Services\TokenDetectionService;
use Roberts\HardhatLaravel\Tests\TestCase;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\NftCollection;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Services\ContractCaller;

it('creates a Token for ERC-20 ABI on populate job', function () {
    /** @var TestCase $this */
    $chain = Blockchain::factory()->create(['chain_id' => 8453]);

    $erc20Abi = [
        ['type' => 'function', 'name' => 'name', 'inputs' => [], 'outputs' => [['type' => 'string']]],
        ['type' => 'function', 'name' => 'symbol', 'inputs' => [], 'outputs' => [['type' => 'string']]],
        ['type' => 'function', 'name' => 'decimals', 'inputs' => [], 'outputs' => [['type' => 'uint8']]],
        ['type' => 'function', 'name' => 'totalSupply', 'inputs' => [], 'outputs' => [['type' => 'uint256']]],
        ['type' => 'function', 'name' => 'balanceOf', 'inputs' => [['type' => 'address']], 'outputs' => [['type' => 'uint256']]],
        ['type' => 'function', 'name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']], 'outputs' => [['type' => 'bool']]],
    ];

    $contract = Contract::query()->create([
        'blockchain_id' => $chain->id,
        'address' => '0x000000000000000000000000000000000000beef',
        'abi' => $erc20Abi,
    ]);

    $caller = m::mock(ContractCaller::class);
    $caller->shouldReceive('call')->andReturn(['MyToken'], ['MTK'], [18], ['1000']);

    $job = new PopulateAssetRecordsJob($contract->id);
    $job->handle($caller, new TokenDetectionService());

    $token = Token::query()->where('contract_id', $contract->id)->first();
    expect($token)->not->toBeNull();
    expect($token->name)->toBe('MyToken');
    expect($token->symbol)->toBe('MTK');
    expect($token->decimals)->toBe(18);
});

it('creates an ERC-721 NftCollection on populate job', function () {
    /** @var TestCase $this */
    $chain = Blockchain::factory()->create(['chain_id' => 8453]);

    $erc721Abi = [
        ['type' => 'function', 'name' => 'name', 'inputs' => [], 'outputs' => [['type' => 'string']]],
        ['type' => 'function', 'name' => 'symbol', 'inputs' => [], 'outputs' => [['type' => 'string']]],
        ['type' => 'function', 'name' => 'ownerOf', 'inputs' => [['type' => 'uint256']], 'outputs' => [['type' => 'address']]],
        ['type' => 'function', 'name' => 'balanceOf', 'inputs' => [['type' => 'address']], 'outputs' => [['type' => 'uint256']]],
        ['type' => 'function', 'name' => 'safeTransferFrom', 'inputs' => [['type' => 'address'], ['type' => 'address'], ['type' => 'uint256']], 'outputs' => []],
    ];

    $contract = Contract::query()->create([
        'blockchain_id' => $chain->id,
        'address' => '0x000000000000000000000000000000000000b0b0',
        'abi' => $erc721Abi,
    ]);

    $caller = m::mock(ContractCaller::class);
    $caller->shouldReceive('call')->andReturn(['CoolNFT'], ['COOL']);

    $job = new PopulateAssetRecordsJob($contract->id);
    $job->handle($caller, new TokenDetectionService());

    $col = NftCollection::query()->where('contract_id', $contract->id)->first();
    expect($col)->not->toBeNull();
    expect($col->standard->value)->toBe('erc721');
    expect($col->name)->toBe('CoolNFT');
    expect($col->symbol)->toBe('COOL');
});

it('creates an ERC-1155 NftCollection with defaults if name/symbol missing', function () {
    /** @var TestCase $this */
    $chain = Blockchain::factory()->create(['chain_id' => 8453]);

    $erc1155Abi = [
        ['type' => 'function', 'name' => 'balanceOf', 'inputs' => [['type' => 'address'], ['type' => 'uint256']], 'outputs' => [['type' => 'uint256']]],
        ['type' => 'function', 'name' => 'balanceOfBatch', 'inputs' => [['type' => 'address[]'], ['type' => 'uint256[]']], 'outputs' => [['type' => 'uint256[]']]],
        ['type' => 'function', 'name' => 'safeTransferFrom', 'inputs' => [['type' => 'address'], ['type' => 'address'], ['type' => 'uint256'], ['type' => 'uint256'], ['type' => 'bytes']], 'outputs' => []],
    ];

    $contract = Contract::query()->create([
        'blockchain_id' => $chain->id,
        'address' => '0x000000000000000000000000000000000000c0de',
        'abi' => $erc1155Abi,
    ]);

    $caller = m::mock(ContractCaller::class);
    // No name/symbol responses (use defaults)
    $caller->shouldReceive('call')->andReturn([], []);

    $job = new PopulateAssetRecordsJob($contract->id);
    $job->handle($caller, new TokenDetectionService());

    $col = NftCollection::query()->where('contract_id', $contract->id)->first();
    expect($col)->not->toBeNull();
    expect($col->standard->value)->toBe('erc1155');
    expect($col->name)->toBe('ERC1155 Collection');
    expect($col->symbol)->toBe('ERC1155');
});
