# Changelog

## [1.0.0] - Unreleased

### Added
- `make:repository` (alias `make:repo`) with `--model` (CRUD methods), `--bind` (auto-maintained `RepositoryServiceProvider`) and `--service`.
- `make:service` with `--repository` injection.
- `make:facade` with `--service` (auto-wired, no manual binding) and `--accessor`.
- `make:enum` (`--type=string|int`), `make:interface`, `make:trait`.
- `make:module` to scaffold application modules (providers, routes, config, migrations, views).
- `--module` / `-m` option on every generator; supports plain `Modules/` and nwidart/laravel-modules layouts.
- `--force` on every generator; existing files are never overwritten silently.
- Publishable config and stubs.
