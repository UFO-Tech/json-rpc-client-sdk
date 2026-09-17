# Repository guidance

## Project overview

This is `ufo-tech/json-rpc-client-sdk`, a PHP library that generates typed JSON-RPC client SDKs and provides their synchronous and asynchronous runtime support. Target PHP 8.3 or newer; Composer defines Symfony 7.x dependencies and PHPUnit 10 for development.

## Code layout

- `src/` uses the `Ufo\RpcSdk\` PSR-4 namespace.
- `src/Maker/` contains SDK generators, schema readers, definitions, configuration objects, and generation helpers.
- `templates/` contains Twig templates for generated procedures, methods, DTOs, enums, and documentation.
- `src/Procedures/` contains runtime calls, configuration, RPC service parameters, async transport resolvers, and response transformation.
- `bin/make.php` is the interactive generation entry point. With a URL argument it reads remote documentation; without one it reads local `bin/schema.json`.
- `tests/` uses `Ufo\RpcSdk\Tests\`; schema, DTO, and enum fixtures live in `tests/Fixtures/`.
- `sdk/` is generated output under `Ufo\RpcSdk\Client\`. Functional tests generate temporary clients in `var/sdk/` under `FunctionalTest\SDK\`.

## Setup and commands

Run commands from the repository root.

```sh
composer install
composer test
vendor/bin/phpunit tests/Maker/MakerTest.php
vendor/bin/phpunit tests/Procedures/ResponseTransformer/SdkResponseCreatorTest.php
vendor/bin/phpunit tests/Functional/GenerateSdkFunctionalTest.php
composer test-coverage
```

Coverage requires a compatible coverage driver. Composer requires the readline and intl extensions; install all platform requirements reported by Composer.

The local `phpunit.xml` bootstraps `vendor/autoload.php`, discovers all of `tests/` (including functional tests), and fails on warnings and risky tests. It is ignored by Git, so a fresh checkout may need an explicit invocation:

```sh
vendor/bin/phpunit --bootstrap vendor/autoload.php tests
```

Tests write cache/log files and functional tests create and remove temporary SDK directories. Run functional tests serially: their directory names use second-resolution timestamps.

Optional Docker workflow: `make up-d`, `make composer-install`, and `make composer CMD=test`. The Makefile uses the legacy `docker-compose` executable; Docker configuration depends on `PROJECT_NAME` and `WORKDIR` environment settings. Inspect local configuration before using it.

## Implementation conventions

- Match nearby PHP formatting: four-space indentation, braces on separate lines for classes and methods, typed properties and signatures, and explicit imports.
- Keep file paths aligned with PSR-4 namespaces. Preserve public runtime APIs and generated method signatures unless the task explicitly changes them.
- Fix generated-code behavior in the relevant makers, definitions, or templates. Do not patch generated SDK files as the source of a fix.
- Keep synchronous and asynchronous generation consistent where they share behavior. Keep RPC service metadata separate from business method arguments.
- For generator changes, add or adapt a focused schema fixture and check generated classes with the functional generation test. For runtime or transformation changes, use focused PHPUnit regression tests.
- Prefer local schema fixtures and mocked transports for tests over live services.
- Run the relevant tests after code changes; run the full suite when changes affect shared generation or runtime behavior. Report checks that could not run and why. Documentation-only edits do not require PHPUnit.

## Working tree and generated artifacts

Check `git status` before editing and preserve existing unrelated work. Treat `vendor/`, `sdk/`, `var/`, and PHPUnit caches as dependencies or generated artifacts. The repository also ignores `composer.lock`, `phpunit.xml`, and `bin/test.php`; do not force-add them as part of routine changes.

Avoid `make commit-a` during routine development: it stages everything, amends the current commit, force-pushes, and can replace remote tags. Use it only when that release/history operation is explicitly requested.
