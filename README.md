# Laravel Package for managing monorepo with Hardhat

[![Latest Version on Packagist](https://img.shields.io/packagist/v/roberts/hardhat-laravel.svg?style=flat-square)](https://packagist.org/packages/roberts/hardhat-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/roberts/hardhat-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/roberts/hardhat-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/roberts/hardhat-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/roberts/hardhat-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/roberts/hardhat-laravel.svg?style=flat-square)](https://packagist.org/packages/roberts/hardhat-laravel)

This Laravel package is designed to control [Hardhat](https://hardhat.org) from a [Laravel](https://laravel.org) application through a monorepo with both applications installed in a specific structure. Please read the documentation below for proper creation and server setup:

See also: [Web3 integration guide](docs/web3.md) and [Hardhat scripts best practices](docs/scripts.md) for using this alongside roberts/web3-laravel.

## Package functionality

- Wrapper around Hardhat, not a replacement: this package doesn’t ship Hardhat (a Node.js tool). It exposes a convenient Laravel API to run the underlying shell commands in your Hardhat project.
- Artisan command(s): provides Artisan commands you can run or schedule to automate workflows (e.g., compile, run scripts, tests; you can also create a scheduled “update” flow that runs `npm update` in your Hardhat project).
- Process execution: uses Laravel’s Process facade to execute commands within your configured Hardhat project directory, enabling PHP to interoperate with Node/Hardhat safely.
- Service provider: registers the commands and a singleton wrapper that manages the path to your Hardhat project and exposes helper methods.
- Configuration: includes a publishable config file to set the path to your Hardhat project (defaults to `base_path('blockchain')`). See the Configuration section below.

## Laravel & Hardhat Monorepo Creation (server-ready)

Before installing this package, you need to create the monorepo structure with an app folder for your Laravel application and a blockchain folder for Hardhat.

Install Laravel within a dedicated subdirectory. This keeps all of Laravel's files and dependencies self-contained. In this layout, your Laravel app lives in `app/` and Hardhat lives in a sibling `blockchain/` folder.

```Bash
composer create-project laravel/laravel app
```

This command creates a new Laravel project in the app directory.

Now, create a separate subdirectory for your Hardhat project and initialize it.

```Bash
mkdir blockchain
cd blockchain
npm init -y
npm install --save-dev hardhat
npx hardhat
```

The `npx hardhat` command prompts you to create a new project. Select the "Create a JavaScript project" or "Create a TypeScript project" option to generate the necessary files, including `hardhat.config.js`, `contracts/`, `scripts/`, and `test/`.

To prevent unnecessary files from being committed to your repository, set up a .gitignore file at the root of your monorepo. This file should tell Git to ignore the node_modules and vendor directories from both projects, as they contain heavy, temporary files.

```
/app/vendor/
/app/.env
/blockchain/node_modules/
/blockchain/cache/
/blockchain/artifacts/
/blockchain/.env
```

Then move the Laravel `.github` folder to the root of your monorepo.

On servers, ensure the `blockchain/` folder exists adjacent to your Laravel application. If your Laravel base path is `/var/www/app`, then Hardhat should live at `/var/www/blockchain` (a sibling directory). You can also point to an absolute path via `HARDHAT_PROJECT_PATH`.

## Installation

Inside the app folder for the Laravel application you can install the package via composer:

```bash
composer require roberts/hardhat-laravel
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="hardhat-laravel-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="hardhat-laravel-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="hardhat-laravel-views"
```

## Path resolution

This package always expects your Hardhat project at a sibling `../blockchain` directory relative to the Laravel base path (ideal for an `app/` + `blockchain/` monorepo). There is no configuration toggle for the path.

Ensure Node.js, npm, and Hardhat are available in the environment. The wrapper executes commands via `npx hardhat` in the resolved directory.

## Usage

Using the Facade:

```php
use Roberts\HardhatLaravel\Facades\Hardhat;

// Compile contracts
$output = Hardhat::compile();

// Run a script with args and environment
$deploy = Hardhat::runScript('scripts/deploy.ts', ['--network', 'sepolia'], [
	'PRIVATE_KEY' => env('PRIVATE_KEY'),
]);

// Non-throwing API
$res = Hardhat::tryRun('compile');
if (! $res->successful()) {
	logger()->warning('Hardhat compile failed', $res->toArray());
}
```

Using dependency injection:

```php
use Roberts\HardhatLaravel\HardhatWrapper;

public function deploy(HardhatWrapper $hardhat)
{
	return $hardhat->runScript('scripts/deploy.ts', ['--network', 'localhost']);
}
```

Errors are surfaced via `Illuminate\Process\Exceptions\ProcessFailedException` when a command exits non‑zero.

### Wrapper example: build deploy data and enqueue a Transaction

The snippet below shows how to call the recommended `scripts/deploy-data.ts` in your Hardhat project, parse its JSON, and enqueue a deploy Transaction using your own app services.

```php
use Roberts\HardhatLaravel\HardhatWrapper;
use Roberts\Web3Laravel\Models\Transaction;

public function deployContract(HardhatWrapper $hardhat): int
{
	// 1) Ask Hardhat for deploy data via a script that defines the contract internally
	//    (recommended for servers: keep artifact/constructor config inside your Hardhat repo)
	$json = $hardhat->runScript('scripts/server/deploy-my-contract.ts');

	/** @var array{artifact:string,abi:array,bytecode:string,constructorArgs:array,data:string} $out */
	$out = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

	// 2) Create a Transaction for contract creation (to = null)
	$tx = Transaction::query()->create([
		'wallet_id' => 1,
		'blockchain_id' => 1,
		'to' => null,
		'value' => '0',
		'data' => $out['data'],
		'function' => 'deploy_contract',
		'function_params' => [
			'artifact' => $out['artifact'],
			'constructor_args' => $out['constructorArgs'],
			'abi_present' => true,
		],
		'meta' => [
			'abi' => $out['abi'],
			'bytecode' => $out['bytecode'],
		],
		'status' => 'pending',
	]);

	// The web3-laravel pipeline will prepare, sign, submit, and confirm this tx.
	// On confirmation, the listener persists a Contract and may auto-verify or populate tokens/nfts.
	return $tx->id;
}
```

	### Eloquent-style helper on Wallet (deployContract)

	For convenience, this package adds a `Wallet::deployContract($artifact, $constructorArgs = [], $opts = [])` macro that invokes the same `evm:deploy` flow and returns the created Transaction id (when parsed from output), for example:

	```php
	use Roberts\Web3Laravel\Models\Wallet;

	$wallet = Wallet::query()->find(1);
	$txId = $wallet->deployContract('<YourArtifactName>', ['arg1', 'arg2'], [
		'chain_id' => 8453,
		'network' => 'base',
		'auto_verify' => true,
	]);
	```

## Testing tips

You can use `Process::fake()` to test your code without invoking Node/Hardhat.

## Artisan commands

- `php artisan hardhat:compile` — runs `npx hardhat compile`
- `php artisan hardhat:run scripts/deploy.ts --arg=--network --arg=sepolia --hh-env=PRIVATE_KEY=...` — runs a script
- `php artisan hardhat:test --arg=--network --arg=localhost` — runs tests (use `--hh-env=KEY=VALUE` for env vars)
- `php artisan hardhat:update` — runs `npm update` in your Hardhat project (supports `--dry-run` and `--silent`)

- `php artisan evm:deploy --artifact="<YourArtifactName>" --script=scripts/deploy-data.ts --args='["arg1"]' --wallet-id=1 --chain-id=8453 --network=base` —
	fetches deploy tx data from a Hardhat helper script, enqueues a Transaction (to=null, data=deployData), and relies on the
	web3-laravel transaction pipeline to sign, broadcast, and confirm. On confirmation, a Contract row is persisted automatically.

Production tip: Prefer a contract-specific Hardhat script (e.g., `scripts/server/deploy-my-contract.ts`) that hard-codes the artifact and constructor settings, then call it from Laravel without passing `--artifact`. See `docs/scripts.md` for patterns.

If your layout differs and `../blockchain` isn’t correct, you’ll need to adjust your server structure to match.

### Scheduling npm update

In `app/Console/Kernel.php` you can schedule the update:

```php
protected function schedule(Schedule $schedule): void
{
	$schedule->command('hardhat:update --silent')->weekly();
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Drew Roberts](https://github.com/drewroberts)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
