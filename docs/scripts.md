# Hardhat scripts best practices (for Laravel integration)

This guide outlines recommended scripts to include in your Hardhat project (../blockchain) so your Laravel app can drive deployments, verification, and encoded calls reliably via this package.

Key goals:
- Deterministic, machine-readable output (JSON to stdout, logs to stderr).
- Stable CLI interface for Laravel to pass args/options.
- No signing/broadcasting in Node: return data to Laravel; Laravel signs and submits through its transaction pipeline.

## Folder and naming

Place scripts in the Hardhat project under `scripts/`:
- scripts/deploy-data.ts
- scripts/verify.ts (optional; Laravel also has a built-in verify wrapper, but you can keep one here for manual use)
- scripts/encode-call.ts (optional; to encode function payloads for non-deploy txs)

Use TypeScript if possible; Hardhat supports both TS and JS. Keep scripts small and focused.

## Common conventions

- Parse CLI with `minimist` and accept predictable flags.
- Print only a single JSON object to stdout on success. Print human logs to stderr.
- Exit with non-zero on failure and include an error message on stderr.
- Import `ethers` from `hardhat` (plugin) to access artifacts and ABI encoding.

Example helper to enforce clean output:

```ts
// scripts/_utils.ts
export function printJson(data: unknown) {
	process.stdout.write(JSON.stringify(data));
}
export function log(...args: unknown[]) {
	console.error('[hh]', ...args);
}
```

## Deploy data script (required)

Laravel uses this to compute the deploy transaction `data` (bytecode + encoded constructor args) and to optionally persist the ABI.

Script: scripts/deploy-data.ts

Inputs:
- --artifact=MyToken (required): Contract name as known to Hardhat artifacts
- --args='["Name","SYM",18]' (optional): Constructor args JSON array

Output JSON (stdout):
```json
{
	"artifact": "MyToken",
	"abi": [/*...*/],
	"bytecode": "0x...",
	"constructorArgs": ["Name","SYM",18],
	"data": "0x..." // full deploy data payload
}
```

Reference implementation:

```ts
// scripts/deploy-data.ts
import { ethers } from "hardhat";
import minimist from "minimist";

async function main() {
	const argv = minimist(process.argv.slice(2));
	const artifactName = String(argv.artifact || '');
	if (!artifactName) throw new Error('--artifact is required');
	const args = JSON.parse(argv.args ?? '[]');

	const artifact = await ethers.artifacts.readArtifact(artifactName);
	const factory = new ethers.ContractFactory(artifact.abi, artifact.bytecode);
	const tx = await factory.getDeployTransaction(...args);

	const out = {
		artifact: artifact.contractName ?? artifactName,
		abi: artifact.abi,
		bytecode: artifact.bytecode,
		constructorArgs: args,
		data: tx.data,
	};
	process.stdout.write(JSON.stringify(out));
}

main().catch((e) => { console.error(e); process.exit(1); });
```

Laravel command that consumes it:
- `php artisan evm:deploy --artifact=MyToken --args='["Name","SYM",18]' --wallet-id=1 --chain-id=8453 --network=base`
- The command uses a wrapper to run `npx hardhat run scripts/deploy-data.ts` and then enqueues a Transaction with `to=null` and `data` equal to the script’s output `data`.

Best practices:
- Keep constructor args JSON-only. Don’t parse env here; pass secrets to Laravel instead.
- Never broadcast from this script; it should be pure and deterministic.

## Encode call script (optional)

Use this to build `data` for contract method calls without broadcasting.

Inputs:
- --artifact=MyToken
- --func=transfer
- --args='["0xRecipient","1000000000000000000"]'

Output JSON:
```json
{ "function": "transfer", "args": ["0x...","1000..."], "data": "0x..." }
```

Implementation sketch:
```ts
// scripts/encode-call.ts
import { ethers } from "hardhat";
import minimist from "minimist";

async function main() {
	const argv = minimist(process.argv.slice(2));
	const artifactName = String(argv.artifact || '');
	const func = String(argv.func || '');
	if (!artifactName || !func) throw new Error('--artifact and --func are required');
	const args = JSON.parse(argv.args ?? '[]');

	const artifact = await ethers.artifacts.readArtifact(artifactName);
	const iface = new ethers.Interface(artifact.abi);
	const data = iface.encodeFunctionData(func, args);
	process.stdout.write(JSON.stringify({ function: func, args, data }));
}

main().catch((e) => { console.error(e); process.exit(1); });
```

Laravel usage:
- Create a Transaction row with `to=<contractAddress>`, `data` from the script, and let the pipeline sign/broadcast.

## Verify script (optional)

Laravel already offers `php artisan evm:verify`, which wraps Hardhat verify. You can keep a script for manual use or custom providers.

Inputs:
- --address=0xContract
- --network=base (or use Hardhat’s `--network` CLI arg)
- --args='[constructorArgs]'

Behavior:
- Call Hardhat’s verify task with the address and args. Print success text or JSON. For Laravel, prefer using the built-in verify command which integrates with jobs and Contract meta updates.

## Multi-network notes

- Laravel infers the Hardhat network from `--network` or `--chain-id`. Your scripts don’t need to handle RPC connectivity beyond reading artifacts and encoding.
- For local dev chains, you can accept `--network localhost` when needed, but keep scripts network-agnostic.

## Output hygiene checklist

- Print a single JSON object to stdout on success.
- Print diagnostics to stderr.
- Don’t mix console.log with JSON on stdout.
- Exit non-zero on failure.

## Security & secrets

- Never embed private keys in scripts. Signing happens in Laravel.
- If a script truly needs env (rare), pass it via Laravel’s `--hh-env` option which sets per-process environment for Hardhat.

## Testing scripts

- Run locally inside `blockchain/`:
```bash
npx hardhat run scripts/deploy-data.ts
```

- Use Laravel’s doctor command to validate Hardhat layout:
```bash
php artisan hardhat:doctor
```

## Troubleshooting

- “Unexpected token in JSON” — ensure scripts print only JSON to stdout.
- “Artifact not found” — confirm contract name matches build output and that `hardhat compile` ran.
- “Data is undefined” — your factory’s `getDeployTransaction` may be failing; check constructor args types/order.

