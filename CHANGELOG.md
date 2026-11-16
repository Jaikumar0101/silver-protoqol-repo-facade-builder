# Changelog

All notable changes to this package are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-16

### Added
- `make:repository` (alias `make:repo`) with `--model` (Eloquent CRUD methods), `--bind` (auto-maintained `RepositoryServiceProvider`) and `--service`.
- `make:service` with `--repository` injection.
- `make:facade` with `--service` (auto-wired, no manual binding) and `--accessor`.
- `make:enum` (`--type=string|int`), `make:interface` and `make:trait`.
- `make:module` to scaffold application modules (service providers, routes, config, migrations, views), with `--autoload` and `--no-register`.
- `--module` / `-m` on every generator, supporting plain `Modules/` and nwidart/laravel-modules layouts (including the v11+ `app/` folder).
- `--force` on every generator; existing files are never overwritten silently.
- Publishable config and stubs.
- Support for Laravel 12 and 13 on PHP 8.2+.

[Unreleased]: https://github.com/Jaikumar0101/silver-protoqol-repo-facade-builder/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/Jaikumar0101/silver-protoqol-repo-facade-builder/releases/tag/v1.0.0
