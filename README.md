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

- PHP 8.1+
- Laravel 10, 11, 12 or 13

## Installation

```bash
composer require silver-protoqol/laravel-repo-facade-builder --dev
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
| `-b`, `--bind` | Adds `Interface::class => Repository::class` to the `$bindings` array of `RepositoryServiceProvider`. The provider is created if it is missing and registered in `bootstrap/providers.php` (Laravel 11+). |
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

> On Laravel 11+ these commands, along with `make:enum`, replace the framework's built-in versions of the same name, so the folder and naming rules stay the same across all your projects.

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

`BlogServiceProvider` is registered in `bootstrap/providers.php` on Laravel 11+. Use `--no-register` to skip this. On Laravel 10, add it to `config/app.php` yourself.

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

## Testing

```bash
composer install
composer test
```

## Credits

Based on [Jaikumar0101/laravel-repo-facade-builder](https://github.com/Jaikumar0101/laravel-repo-facade-builder).

## License

MIT. See [LICENSE](LICENSE).
