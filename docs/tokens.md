# Token and NFT detection (auto-populating assets)

This package can automatically infer whether a newly deployed contract is a fungible token (ERC‑20) or an NFT collection (ERC‑721 / ERC‑1155) and create the appropriate database records. The flow is fully event‑driven and happens after a deploy transaction is confirmed.

## When detection runs

1) A deploy is initiated via `php artisan evm:deploy ...` (or `Wallet::deployArtifact()` macro).
2) The transaction is submitted and later confirmed by the web3‑laravel pipeline.
3) The listener `PersistDeployedContract` creates a `contracts` row from the receipt (address, blockchain, creator) and persists the ABI if present on the transaction meta.
4) If an ABI is present, the listener dispatches `PopulateAssetRecordsJob` with the `contract_id`.

Nothing runs if there’s no ABI. If the ABI is provided later, you can queue the job manually (see manual usage).

## How detection works

Service: `Roberts\HardhatLaravel\Services\TokenDetectionService`

Heuristics use ABI function signatures to identify the standard:

- ERC‑20 detection requires typical functions: `totalSupply`, `decimals`, `symbol`, `name`, and `balanceOf(address)`.
- ERC‑721 detection looks for `ownerOf`, `balanceOf` and either `safeTransferFrom` or `transferFrom`.
- ERC‑1155 detection looks for `balanceOf(address,uint256)`, `balanceOfBatch`, and `safeTransferFrom(address,address,uint256,uint256)`.

If none match, no token/NFT records are created.

## What gets created

- For ERC‑20: a row in `tokens` linked to the `contracts` row.
	- The job attempts to read `name`, `symbol`, `decimals`, and `totalSupply` using `ContractCaller`. If any call fails, it falls back to defaults: `name="Token"`, `symbol="TKN"`, `decimals=18`, `total_supply="0"`.

- For ERC‑721 or ERC‑1155: a row in `nft_collections` linked to the `contracts` row.
	- The job attempts to read `name` and `symbol`. If missing:
		- ERC‑721: defaults to `name="NFT Collection"`, `symbol="NFT"`.
		- ERC‑1155: defaults to `name="ERC1155 Collection"`, `symbol="ERC1155"`.
	- The `standard` column is set to `erc721` or `erc1155` accordingly.

## Jobs involved

- `PersistDeployedContract` (listener)
	- On `TransactionConfirmed`, persists `contracts` and, if ABI exists, dispatches:
		- `PopulateAssetRecordsJob($contractId)`

- `PopulateAssetRecordsJob`
	- Loads the contract by id.
	- Calls `TokenDetectionService::detect($abi)`.
	- Creates a `Token` or `NftCollection` accordingly, using `ContractCaller` to fetch optional metadata with safe fallbacks.

## Manual usage

If you have a contract with an ABI saved later or want to re‑run detection:

```php
use Roberts\HardhatLaravel\Jobs\PopulateAssetRecordsJob;

PopulateAssetRecordsJob::dispatch($contractId);
```

Requirements:
- The `contracts` row must have a valid `abi` JSON and `address`.
- The target blockchain/RPC must be reachable for `ContractCaller` to resolve name/symbol/etc.

## Error handling and idempotency

- The job uses `firstOrCreate` for both `tokens` and `nft_collections`, so re‑runs are safe.
- If on‑chain calls fail (e.g., RPC issues), defaults are used and no exception is thrown. You can re‑run the job later to try to enrich missing fields.

## Configuration and customization

While the detection heuristics are intentionally simple and robust, you can extend or replace them:

- Bind your own implementation of `TokenDetectionService` in a service provider if your contracts require different signatures.
- Hook your own listener after `PopulateAssetRecordsJob` completes to enrich with off‑chain metadata or media.

## Troubleshooting

- “No records created”
	- Ensure the `contracts.abi` column is populated. Without an ABI, detection is skipped.
	- Confirm that the ABI actually contains the expected function signatures. Some proxies/minimal contracts won’t expose them.

- “Defaults used instead of real metadata”
	- The contract may not implement `name`, `symbol`, or `decimals`, or the RPC call failed. Check logs and your RPC credentials.

- “Wrong standard detected”
	- Provide a minimal reproduction ABI and consider adjusting the heuristics in `TokenDetectionService`.

## Related docs

- Overview of the deployment and confirmation flow: [web3.md](./web3.md)

