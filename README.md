# Laravel Package for managing monorepo with Hardhat

[![Latest Version on Packagist](https://img.shields.io/packagist/v/roberts/hardhat-laravel.svg?style=flat-square)](https://packagist.org/packages/roberts/hardhat-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/roberts/hardhat-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/roberts/hardhat-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/roberts/hardhat-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/roberts/hardhat-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/roberts/hardhat-laravel.svg?style=flat-square)](https://packagist.org/packages/roberts/hardhat-laravel)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Laravel & Hardhat Installation

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

## Usage

```php
$hardhatLaravel = new Roberts\HardhatLaravel();
echo $hardhatLaravel->echoPhrase('Hello, Roberts!');
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
