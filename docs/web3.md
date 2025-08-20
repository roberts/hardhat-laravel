# Web3 integration (roberts/web3-laravel)

This package pairs well with the roberts/web3-laravel package, which provides a protocol‑first, chain‑agnostic toolkit for blockchain operations. In particular, it offers a robust EVM path (Ethereum‑compatible) for creating wallets, sending native value, and interacting with ERC‑20 tokens.

This document summarizes that package’s database structure and how EVM operations execute from the wallets it manages. Use it as a reference when wiring your monorepo (Laravel + Hardhat) so on‑chain actions and off‑chain tooling stay in sync.

- Package: https://packagist.org/packages/roberts/web3-laravel
- Repo: https://github.com/roberts/web3-laravel

## Where your Solidity contracts live (Hardhat application)

We standardize on keeping all Solidity sources inside a Hardhat project located at a sibling path to your Laravel app:

Monorepo layout:

```
laravel-app/           # your Laravel application (this package installed here)
blockchain/            # Hardhat project (canonical Solidity sources and scripts)
	├─ contracts/
	├─ scripts/
	├─ hardhat.config.ts (or .js)
	└─ package.json
```

This package expects Hardhat at `../blockchain` relative to the Laravel base path and will run Hardhat commands there. Contracts are compiled, deployed, and verified from this Hardhat workspace. Laravel never writes ABI files to disk; ABIs are stored only in the `contracts.abi` JSON column when a deploy confirms.

Why this setup:

- Clear separation: Hardhat owns Solidity toolchain; Laravel owns wallet custody and on‑chain execution pipeline.
- Reproducible builds and verification via Hardhat.
- Simpler CI and server layout; our diagnostics (`php artisan hardhat:doctor`) assume this path.

Advanced: If you need reusable contracts across apps, publish them as an npm package (e.g., `@org/contracts`) and consume them from the Hardhat project. Avoid duplicating sources across Laravel or this package.

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

## Contract deployment using the Transaction pipeline (no deployments table)

Instead of adding a separate deployments table, leverage the existing Transaction model and EVM adapter to broadcast deploy transactions. Hardhat remains the compile/verify tool; Laravel signs and submits the deployment as a normal EVM transaction (to=null, data=bytecode+constructor encoding).

High‑level flow:

1) Compile with Hardhat
- Use Hardhat to compile your contracts. You’ll read ABI/bytecode from the artifact JSON.

2) Build deploy data (preferred: from a small Hardhat helper)
- Create a tiny Hardhat script (e.g., scripts/deploy-data.ts) that loads the artifact and computes the deploy transaction data via ethers:
	- const factory = new ethers.ContractFactory(abi, bytecode);
	- const tx = await factory.getDeployTransaction(...args);
	- console.log(JSON.stringify({ artifact, abi, bytecode, constructorArgs: args, data: tx.data }));
- Your Laravel command runs this script via HardhatWrapper and parses the JSON. The critical field is data (the full deploy data, i.e., bytecode concatenated with ABI‑encoded constructor args).

Example `scripts/deploy-data.ts` (in your Hardhat `blockchain/` project):

```ts
import { ethers } from "hardhat";

async function main() {
	// Parse CLI flags like --artifact and --args='["Name","SYM"]'
	const argv = require('minimist')(process.argv.slice(2));
	const artifactName: string = argv.artifact;
	const args = JSON.parse(argv.args ?? '[]');

	const artifact = await ethers.artifacts.readArtifact(artifactName);
	const factory = new ethers.ContractFactory(artifact.abi, artifact.bytecode);
	const tx = await factory.getDeployTransaction(...args);

	const out = {
		artifact: artifact.contractName,
		abi: artifact.abi,
		bytecode: artifact.bytecode,
		constructorArgs: args,
		data: tx.data,
	};
	console.log(JSON.stringify(out));
}

main().catch((e) => { console.error(e); process.exit(1); });
```

3) Enqueue a Transaction row
- Create a Transaction with:
	- wallet_id: signer wallet (custodial/shared with key stored)
	- blockchain_id/chain_id: target chain
	- to: null (contract creation)
	- value: '0' (unless your constructor is payable)
	- data: the deploy data from step 2
	- is_1559 and gas fields as desired (or leave for the adapter/service to fill defaults)
	- function: 'deploy_contract' (label for analytics)
	- function_params: { artifact, constructor_args, abi_present: true }
	- meta: optionally stash abi and bytecode for later persistence/verification
- Let the existing prepare job fill nonce and chain defaults; submit job will sign and send; confirm job will track status.

4) Persist the Contract after confirmation
- In your confirmation handling (listener or a small post‑confirm hook), fetch the receipt via EvmProtocolAdapter::checkConfirmations. When confirmed, the receipt will contain contractAddress.
- Upsert a Contract row with address=contractAddress, blockchain_id, creator=wallet.address, and abi (take from meta/artifact).
- Update the Transaction.contract_id and store receipt details in Transaction.meta.

5) Optional: Token/NFT specialization
- After confirmation, this package can auto‑detect ERC‑20/721/1155 from the ABI and create the right records (`tokens` or `nft_collections`).
- See Token & NFT auto‑detection details: [docs/tokens.md](./tokens.md)

6) Verification via Hardhat
- After confirmation, kick a Hardhat verify script with the address and constructor args. Update Contract.meta with verified=true and a verification URL (if available). You can store verification results in the Transaction.meta as well.

### Data handoff contract between Hardhat and Laravel

From scripts/deploy-data.ts (example):

{
	"artifact": "MyToken",
	"abi": [ ... ],
	"bytecode": "0x...",
	"constructorArgs": ["arg1", "arg2"],
	"data": "0x..." // full deploy data used as Transaction.data
}

Laravel only needs data to enqueue the Transaction. Keeping abi/bytecode/args helps later persistence and verification.

### Orchestrating from this package

- Command: `php artisan evm:deploy --artifact=MyToken --args='["foo"]' --wallet-id=1 --chain-id=8453 --network=base`
	- Uses HardhatWrapper to run a helper script (default `scripts/deploy-data.ts`) that prints the JSON above.
	- Creates a Transaction (to=null, data=deploy data) and lets the pipeline sign/broadcast/confirm.
	- A built-in listener persists a Contract row on TransactionConfirmed using receipt.contractAddress and the ABI from tx.meta.

Optional flags and follow‑ups:

- `--auto-verify`: when set, after the deploy confirms and the Contract is persisted, a `VerifyContractJob` is queued automatically. Network is inferred from `--network` or `--chain-id`.
- Manual verify: `php artisan evm:verify 0xDeployedAddress --chain-id=8453` (or `--network=base`). Add `--queue` with `--contract-id` to run in the background.

Notes:

- Contracts must exist in the Hardhat `blockchain/` project. This package does not read Solidity from Laravel folders or from this package itself.
- ABIs are not written to disk by Laravel; they’re saved to the `contracts.abi` column when the transaction confirms.

### CREATE2 option

- If you need deterministic addresses, include a salt and factory address in function_params/meta.
- You can precompute the address off‑chain and store it in Transaction.meta.precomputed_address for idempotency checks. The actual deploy still uses data and to=null.

### Error handling and idempotency

- Duplicate broadcasts are naturally reduced by nonce control and tx_hash uniqueness.
- If a prior deploy succeeded, your post‑confirm hook can detect an existing Contract at the precomputed or receipt address and skip duplicate inserts.

### Why this approach

- Reuses the existing prepare → submit → confirm pipeline, events, and retries.
- Keeps signing in Laravel (custodial/shared wallets) while still using Hardhat for compile/verify.
- Avoids introducing a new deployments table; Contract + Transaction records are sufficient for most workflows.

## Troubleshooting & diagnostics

- Validate your Hardhat setup and path with:

```bash
php artisan hardhat:doctor
```

- Ensure `blockchain/` exists one level up from your Laravel base path and has a valid `hardhat.config.ts` or `.js`.

## FAQ

- Can I keep Solidity inside the Laravel app or this package?
	- Not recommended. Keep Solidity in the Hardhat project. If you need to reuse contracts, ship them as an npm dependency consumed by the Hardhat project.
- Where are ABIs stored?
	- In the database (`contracts.abi`). We do not write ABI files to disk from Laravel.

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

