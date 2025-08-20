# Laravel Package for managing monorepo with Hardhat

[![Latest Version on Packagist](https://img.shields.io/packagist/v/roberts/hardhat-laravel.svg?style=flat-square)](https://packagist.org/packages/roberts/hardhat-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/roberts/hardhat-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/roberts/hardhat-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/roberts/hardhat-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/roberts/hardhat-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/roberts/hardhat-laravel.svg?style=flat-square)](https://packagist.org/packages/roberts/hardhat-laravel)

This Laravel package is designed to control [Hardhat](https://hardhat.org) from a [Laravel](https://laravel.org) application through a monorepo with both applications installed in a specific structure. Please read the documentation below for proper creation:

See also: [Web3 integration guide](docs/web3.md) for using this alongside roberts/web3-laravel.

## Package functionality

- Wrapper around Hardhat, not a replacement: this package doesn’t ship Hardhat (a Node.js tool). It exposes a convenient Laravel API to run the underlying shell commands in your Hardhat project.
- Artisan command(s): provides Artisan commands you can run or schedule to automate workflows (e.g., compile, run scripts, tests; you can also create a scheduled “update” flow that runs `npm update` in your Hardhat project).
- Process execution: uses Laravel’s Process facade to execute commands within your configured Hardhat project directory, enabling PHP to interoperate with Node/Hardhat safely.
- Service provider: registers the commands and a singleton wrapper that manages the path to your Hardhat project and exposes helper methods.
- Configuration: includes a publishable config file to set the path to your Hardhat project (defaults to `base_path('blockchain')`). See the Configuration section below.

## Laravel & Hardhat Monorepo Creation

Before installing this package, you need to create the monorepo structure with an app folder for your Laravel application and a blockchain folder for Hardhat.

Install Laravel within a dedicated subdirectory. This keeps all of Laravel's files and dependencies self-contained.

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

The npx hardhat command prompts you to create a new project. Select the "Create a JavaScript project" or "Create a TypeScript project" option to generate the necessary files, including hardhat.config.js, contracts/, scripts/, and test/.

To prevent unnecessary files from being committed to your repository, set up a .gitignore file at the root of your monorepo. This file should tell Git to ignore the node_modules and vendor directories from both projects, as they contain heavy, temporary files.

```
/app/vendor/
/app/.env
/blockchain/node_modules/
/blockchain/cache/
/blockchain/artifacts/
/blockchain/.env
```

Then move the Laravel .github folder to the root of your monorepo.

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

## Configuration

Publish the config and set your Hardhat project path (defaults to `base_path('blockchain')`):

```php
// config/hardhat-laravel.php
return [
	'project_path' => env('HARDHAT_PROJECT_PATH', base_path('blockchain')),
];
```

Ensure Node.js, npm, and Hardhat are available in the environment. The wrapper executes commands via `npx hardhat` in the configured directory.

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

## Testing tips

You can use `Process::fake()` to test your code without invoking Node/Hardhat.

## Artisan commands

- `php artisan hardhat:compile` — runs `npx hardhat compile`
- `php artisan hardhat:run scripts/deploy.ts --arg=--network --arg=sepolia --env=PRIVATE_KEY=...` — runs a script
- `php artisan hardhat:test --arg=--network --arg=localhost` — runs tests
- `php artisan hardhat:update` — runs `npm update` in your Hardhat project (supports `--dry-run` and `--silent`)

- `php artisan web3:deploy --artifact=MyToken --args='["arg1"]' --wallet-id=1 --chain-id=8453 --network=base` —
	fetches deploy tx data from a Hardhat helper script, enqueues a Transaction (to=null, data=deployData), and relies on the
	web3-laravel transaction pipeline to sign, broadcast, and confirm. On confirmation, a Contract row is persisted automatically.

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
