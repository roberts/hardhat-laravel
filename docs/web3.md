# Web3 integration (roberts/web3-laravel)

This package pairs well with the roberts/web3-laravel package, which provides a protocol‑first, chain‑agnostic toolkit for blockchain operations. In particular, it offers a robust EVM path (Ethereum‑compatible) for creating wallets, sending native value, and interacting with ERC‑20 tokens.

This document summarizes that package’s database structure and how EVM operations execute from the wallets it manages. Use it as a reference when wiring your monorepo (Laravel + Hardhat) so on‑chain actions and off‑chain tooling stay in sync.

- Package: https://packagist.org/packages/roberts/web3-laravel
- Repo: https://github.com/roberts/web3-laravel

## Database schema overview

Core tables used by EVM and other protocols:

- blockchains
	- id, name, abbreviation, chain_id (EVM), rpc, scanner, protocol (evm|solana|…), supports_eip1559, native_symbol/decimals, rpc_alternates (json), is_active, is_default, timestamps
	- unique(chain_id). Defines networks and RPC endpoints for routing.

- wallets
	- id, address (unique), key (encrypted), wallet_type (custodial|shared|external), owner_id (users.id), protocol (includes evm), network, public_key, derivation_path, key_scheme, is_active, last_used_at, account_status, meta (json), timestamps
	- EVM addresses are stored normalized (lowercase) and presented checksummed via the accessor. Keys are encrypted with Laravel Crypt.

- contracts
	- id, blockchain_id (nullable FK), address (unique), creator, abi (json), timestamps
	- One row per deployed on‑chain contract, optionally linked to a blockchain row.

- tokens
	- id, contract_id (FK, cascade), symbol, name, decimals, total_supply, optional metadata and market fields, timestamps
	- unique(contract_id). One fungible token per contract.

- transactions
	- id, wallet_id (FK), blockchain_id (nullable), contract_id (nullable)
	- to/from, function, function_params (json), value (wei string), token_quantity (wei string)
	- gas_limit, gwei, fee_max, priority_max, is_1559, nonce, chain_id, data, access_list
	- status (pending|submitted|confirmed|failed), tx_hash (unique nullable), error, meta (json), timestamps
	- Designed for EVM (EIP‑1559 + legacy) and re‑used across protocols.

Other tables (key_releases, nft_collections, wallet_nfts, wallet_tokens) support custody auditing and portfolio tracking.

## EVM execution model

- ProtocolRouter
	- Maps protocol → adapter. For EVM, resolves EvmProtocolAdapter.

- EvmProtocolAdapter
	- createWallet: generates secp256k1 keypair, derives address (Keccak(pubkey)), encrypts and stores the private key.
	- getNativeBalance: eth_getBalance(latest).
	- transferNative: builds a minimal transaction payload (to/value/gas and optional gasPrice) and delegates signing/broadcast to TransactionService.
	- ERC‑20 flows: balanceOf/allowance via ContractCaller; transfer/approve/revoke enqueue Transactions through TokenService.
	- prepareTransaction: ensures chain_id (defaults from config) and fills nonce via eth_getTransactionCount(pending) when missing.
	- submitTransaction: builds EIP‑1559/legacy payload from the Transaction row and calls TransactionService::sendRaw to sign + send.
	- checkConfirmations: compares head block vs receipt block against confirmations_required from config.

- EvmClientInterface
	- Abstraction over JSON‑RPC: getBalance, gasPrice, estimateGas, getTransactionCount, sendRawTransaction, call, blockNumber, receipts, logs, code, storage, feeHistory, etc.
	- Bound in the service provider to a native JSON‑RPC client with retries/backoff.

- Transaction pipeline (async)
	- Transactions flow through prepare → submit → confirm jobs, updating status and tx_hash, and emitting events. This keeps network calls off the request thread and centralizes error handling.

## Configuration notes

- Default RPC & chain:
	- config('web3-laravel.default_rpc') and default_chain_id (e.g., Base mainnet) are used when a chain_id isn’t provided.
	- config('web3-laravel.networks') can map chainId → RPC and override database rows if desired.

- Confirmations:
	- config('web3-laravel.confirmations_required') and confirmations_poll_interval tune confirmation checks.

## Typical EVM usage

Creating a wallet, transferring native value, and performing ERC‑20 actions are all done via services and the EVM adapter. High‑level flow:

1) Create an EVM wallet

```php
use Roberts\Web3Laravel\Models\Wallet;

$wallet = Wallet::evm(); // custodial by default; stores encrypted key
```

2) Send native value (async raw send under the hood)

```php
/** @var Roberts\Web3Laravel\Protocols\Evm\EvmProtocolAdapter $evm */
$evm = app(Roberts\Web3Laravel\Protocols\Evm\EvmProtocolAdapter::class);

$txId = $evm->transferNative($wallet, '0xRecipient...', '10000000000000000'); // 0.01 ETH in wei
```

3) ERC‑20 approve/transfer (creates Transaction rows handled by the jobs)

```php
use Roberts\Web3Laravel\Models\Token;

/** @var Token $token */
$txApproveId = $evm->approveToken($token, $wallet, '0xSpender...', '1000000000000000000'); // 1 token (18 decimals)
$txTransferId = $evm->transferToken($token, $wallet, '0xRecipient...', '1000000000000000000');
```

The created Transaction records are later submitted and confirmed by the pipeline, and tx_hash is populated on success.

## How this package complements web3‑laravel

This package focuses on orchestrating Hardhat commands (compile, run scripts, tests, npm update) from Laravel. In a monorepo setup:

- Use web3‑laravel to manage blockchain accounts, transactions, and on‑chain state (especially EVM actions via its adapters).
- Use this package to automate your Hardhat workflows (contract compilation, deployments, tests) against the same repositories/networks.
- Together, you get a clean separation: Laravel services for wallet custody and on‑chain execution, and Node tooling for development/devops of smart contracts.

