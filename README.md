# Laravel Repo Facade Builder

Artisan generators for **repositories, services, facades, enums, interfaces and traits**, built for both standard and **modular** Laravel apps. Maintained by [Silver-ProtoQoL](https://github.com/Silver-ProtoQoL).

```bash
php artisan make:module Blog
php artisan make:repository Post --module=Blog --model=Post --bind --service
```

That gives you a module with its own service providers, a `PostRepositoryInterface`, a `PostRepository` with CRUD methods, a `PostService` that receives the interface, and a binding that is registered automatically.

## Features

- **Repository pattern**: interface and implementation, optional Eloquent CRUD (`--model`), automatic container binding (`--bind`)
- **Services** that can have a repository injected (`--repository`)
- **Facades** that can create their own backing service, so you don't have to bind anything by hand (`--service`)
- **Enums** (pure, `string` or `int` backed), **interfaces** and **traits**
- **Modules**: `make:module`, plus a `--module` option on every generator. Works with a plain `Modules/` folder and with [nwidart/laravel-modules](https://github.com/nWidart/laravel-modules), including its v11+ `app/` layout
- Sub-folders and namespaces: `Accounting/Bill/CreditNote` becomes `App\Repositories\Accounting\Bill`
- Safe by default: existing files are never overwritten unless you pass `--force`
- Customisable folders, module layout and stubs

## Requirements

| Package | PHP | Laravel |
| --- | --- | --- |
| 1.x | 8.2 – 8.5 | 12, 13 |

Laravel 10 and 11 are not supported. Both are past end of life, and Composer refuses to install them by default because of unpatched security advisories.

## Installation

```bash
composer require silver-protoqol/laravel-repo-facade-builder:^1.0 --dev
```

Package discovery registers the service provider for you.

Optional publishing:

```bash
php artisan vendor:publish --tag=repo-facade-builder-config
php artisan vendor:publish --tag=repo-facade-builder-stubs
```

## Commands

Every generator accepts these options:

| Option | Description |
| --- | --- |
| `-m`, `--module=Blog` | Generate inside a module instead of `app/` |
| `-f`, `--force` | Overwrite existing files |

### `make:repository` (alias `make:repo`)

```bash
php artisan make:repository User
php artisan make:repository Accounting/Bill/CreditNote
php artisan make:repository User --model=User --bind --service
```

| Option | Description |
| --- | --- |
| `--model=User` | Injects the model and generates `all`, `paginate`, `find`, `findOrFail`, `create`, `update` and `delete` on the class and its interface. A short name resolves to `App\Models\User`, or to `Modules\{Module}\Models\User` inside a module. A fully-qualified class name also works. |
| `-b`, `--bind` | Adds `Interface::class => Repository::class` to the `$bindings` array of `RepositoryServiceProvider`. The provider is created if it is missing and registered in `bootstrap/providers.php`. |
| `-s`, `--service` | Also creates `UserService`, which receives `UserRepositoryInterface` in its constructor. |

`UserRepository` as input gives the same result as `User`, so the suffix is never doubled.

Generated provider:

```php
class RepositoryServiceProvider extends ServiceProvider
{
    public $bindings = [
        \App\Repositories\UserRepositoryInterface::class => \App\Repositories\UserRepository::class,
        // @repo-facade-builder:bindings
    ];
}
```

> Keep the `// @repo-facade-builder:bindings` marker. New bindings are inserted above it.

### `make:service`

```bash
php artisan make:service Payment/Stripe              # App\Services\Payment\StripeService
php artisan make:service Order --repository=Order    # injects OrderRepositoryInterface
```

### `make:facade`

```bash
php artisan make:facade Payment                        # accessor 'payment' (you bind it)
php artisan make:facade Payment --service              # creates PaymentService and returns PaymentService::class
php artisan make:facade Cart --accessor=shop.cart      # custom container key
```

With `--service` the facade returns the service's class name, which Laravel's container resolves on its own. You don't need to bind anything.

### `make:enum`

```bash
php artisan make:enum Status
php artisan make:enum Orders/OrderStatus --type=string
php artisan make:enum Priority --type=int
```

### `make:interface` / `make:trait`

```bash
php artisan make:interface Payments/Gateway   # App\Interfaces\Payments\GatewayInterface
php artisan make:trait Concerns/HasUuid       # App\Traits\Concerns\HasUuid
```

> These commands, along with `make:enum`, replace the framework's built-in versions of the same name, so the folder and naming rules stay the same across all your projects.

### `make:module`

```bash
php artisan make:module Blog
php artisan make:module Blog --autoload   # also adds "Modules\\": "Modules/" to composer.json
```

Creates:

```
Modules/Blog/
├── Http/Controllers/
├── Models/
├── Providers/
│   ├── BlogServiceProvider.php        # loads routes, views, migrations, config; registers ↓
│   └── RepositoryServiceProvider.php  # target of --bind
├── Repositories/
├── Services/
├── config/config.php
├── database/migrations/
├── resources/views/                   # view('blog::...')
└── routes/
    ├── api.php                        # prefixed /api/blog
    └── web.php
```

`BlogServiceProvider` is registered in `bootstrap/providers.php`. Use `--no-register` to skip this.

If `composer.json` has no PSR-4 entry for the modules namespace, the command tells you what to add. Pass `--autoload` to have it added for you, then run `composer dump-autoload`.

### Modular workflow

```bash
php artisan make:module Shop

php artisan make:repository Product -m Shop --model=Product --bind --service
php artisan make:facade Cart        -m Shop --service
php artisan make:enum OrderStatus   -m Shop --type=string
php artisan make:interface PaymentGateway -m Shop
php artisan make:trait HasCart      -m Shop
```

| Layout | Files go to | Namespace |
| --- | --- | --- |
| Plain / nwidart v10 (`Modules/Shop/...`) | `Modules/Shop/Repositories` | `Modules\Shop\Repositories` |
| nwidart v11+ (`Modules/Shop/app/...`) | `Modules/Shop/app/Repositories` | `Modules\Shop\Repositories` |

The layout is detected for you: if a module has an `app/` folder, classes go there. Module names are matched case-insensitively, so `--module=shop` works.

## Configuration

`config/repo-facade-builder.php`:

```php
'paths' => [
    'repository' => 'Repositories',
    'service'    => 'Services',
    'facade'     => 'Facades',
    'enum'       => 'Enums',
    'interface'  => 'Interfaces',   // e.g. change to 'Contracts'
    'trait'      => 'Traits',       // e.g. change to 'Concerns'
    'provider'   => 'Providers',
    'model'      => 'Models',
],

'repository' => [
    'interface_folder' => null,      // 'Contracts' → Repositories/Contracts/UserRepositoryInterface.php
    'provider' => 'RepositoryServiceProvider',
],

'modules' => [
    'path' => 'Modules',             // relative to base_path()
    'namespace' => 'Modules',
    'app_folder' => 'auto',          // 'auto' | '' | 'app'
    'scaffold' => ['Http/Controllers', 'Models', 'Repositories', 'Services', 'Providers'],
],

'stubs_path' => base_path('stubs/repo-facade-builder'),
```

## Custom stubs

After `vendor:publish --tag=repo-facade-builder-stubs`, edit any file in `stubs/repo-facade-builder/`. A stub you delete falls back to the package default.

Available placeholders include `{{ namespace }}`, `{{ class }}`, `{{ imports }}`, `{{ interface }}`, `{{ model }}`, `{{ repository }}` and `{{ accessor }}`. Module stubs also get `{{ module }}`, `{{ moduleKebab }}` and `{{ moduleSnake }}`.

## Development

```bash
composer install
composer test       # PHPUnit: unit and feature tests
composer analyse    # PHPStan (Larastan), level 8
composer lint       # Pint, check only
composer format     # Pint, fix style
composer check      # all of the above
```

CI runs style and static analysis, then the test suite on PHP 8.2–8.5 with Laravel 12 and 13. Each combination runs with both the lowest and the latest allowed dependencies, plus one Windows job.

## Versioning

This package follows [Semantic Versioning](https://semver.org):

- **Patch** (`1.0.x`): bug fixes. Generated output only changes to fix a bug.
- **Minor** (`1.x.0`): new commands, options or stub placeholders, all backwards compatible.
- **Major** (`x.0.0`): breaking changes, such as removed options, changed defaults, changes to generated code that would break custom stubs, or dropping a Laravel or PHP version.

All changes are recorded in [CHANGELOG.md](CHANGELOG.md). `main` is aliased to `1.x-dev`.

### Releasing

1. Move the entries under `## [Unreleased]` in `CHANGELOG.md` into a new `## [X.Y.Z] - YYYY-MM-DD` section and update the compare links at the bottom.
2. Commit, then tag and push:

   ```bash
   git tag -a vX.Y.Z -m "vX.Y.Z"
   git push origin vX.Y.Z
   ```

3. The **Release** workflow runs the full test matrix, then publishes a GitHub release using that version's changelog section. It fails if the section is missing. Tags with a suffix such as `v1.1.0-beta.1` are published as pre-releases.

## Credits

Based on [Jaikumar0101/laravel-repo-facade-builder](https://github.com/Jaikumar0101/laravel-repo-facade-builder).

## License

MIT. See [LICENSE](LICENSE).
