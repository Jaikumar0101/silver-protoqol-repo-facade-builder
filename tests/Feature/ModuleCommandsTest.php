<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Modules\Inventory\Providers\InventoryServiceProvider;
use Modules\Inventory\Repositories\StockRepository;
use Modules\Inventory\Repositories\StockRepositoryInterface;
use Modules\Inventory\Services\StockService;
use PHPUnit\Framework\Attributes\Test;
use SilverProtoQoL\RepoFacadeBuilder\Tests\TestCase;

class ModuleCommandsTest extends TestCase
{
    #[Test]
    public function it_scaffolds_a_module(): void
    {
        $this->artisan('make:module', ['name' => 'blog'])->assertSuccessful();

        $provider = base_path('Modules/Blog/Providers/BlogServiceProvider.php');
        $this->assertFileContains($provider,
            'namespace Modules\Blog\Providers;',
            'class BlogServiceProvider extends ServiceProvider',
            '$this->app->register(RepositoryServiceProvider::class);',
            "return __DIR__.'/..'");
        $this->assertValidPhp($provider);

        $repositoryProvider = base_path('Modules/Blog/Providers/RepositoryServiceProvider.php');
        $this->assertFileContains($repositoryProvider, 'namespace Modules\Blog\Providers;', 'public $bindings = [');
        $this->assertValidPhp($repositoryProvider);

        $this->assertDirectoryExists(base_path('Modules/Blog/Repositories'));
        $this->assertDirectoryExists(base_path('Modules/Blog/Http/Controllers'));
        $this->assertFileExists(base_path('Modules/Blog/routes/web.php'));
        $this->assertFileExists(base_path('Modules/Blog/routes/api.php'));
        $this->assertFileExists(base_path('Modules/Blog/config/config.php'));
    }

    #[Test]
    public function it_refuses_to_overwrite_an_existing_module(): void
    {
        $this->makeModuleFolder('Blog');

        $this->artisan('make:module', ['name' => 'Blog'])->assertFailed();
    }

    #[Test]
    public function generators_fail_for_unknown_modules(): void
    {
        $this->artisan('make:repository', ['name' => 'Post', '--module' => 'Missing'])->assertFailed();

        $this->assertDirectoryDoesNotExist(base_path('Modules/Missing'));
    }

    #[Test]
    public function it_generates_a_full_repository_stack_inside_a_module(): void
    {
        $this->artisan('make:module', ['name' => 'Blog'])->assertSuccessful();

        $this->artisan('make:repository', [
            'name' => 'Post',
            '--module' => 'blog',
            '--model' => 'Post',
            '--bind' => true,
            '--service' => true,
        ])->assertSuccessful();

        $this->assertFileContains(base_path('Modules/Blog/Repositories/PostRepository.php'),
            'namespace Modules\Blog\Repositories;',
            'use Modules\Blog\Models\Post;',
            'class PostRepository implements PostRepositoryInterface');
        $this->assertFileContains(base_path('Modules/Blog/Services/PostService.php'),
            'namespace Modules\Blog\Services;',
            'use Modules\Blog\Repositories\PostRepositoryInterface;');
        $this->assertFileContains(base_path('Modules/Blog/Providers/RepositoryServiceProvider.php'),
            '\Modules\Blog\Repositories\PostRepositoryInterface::class => \Modules\Blog\Repositories\PostRepository::class,');
        $this->assertValidPhp(base_path('Modules/Blog/Providers/RepositoryServiceProvider.php'));

        // Nothing leaks into app/.
        $this->assertFileDoesNotExist(app_path('Providers/RepositoryServiceProvider.php'));
        $this->assertDirectoryDoesNotExist(app_path('Repositories'));
    }

    #[Test]
    public function every_generator_supports_the_module_option(): void
    {
        $this->makeModuleFolder('Shop');

        $this->artisan('make:service', ['name' => 'Checkout', '-m' => 'Shop'])->assertSuccessful();
        $this->artisan('make:facade', ['name' => 'Cart', '--module' => 'Shop', '--service' => true])->assertSuccessful();
        $this->artisan('make:enum', ['name' => 'OrderStatus', '--module' => 'Shop', '--type' => 'string'])->assertSuccessful();
        $this->artisan('make:interface', ['name' => 'PaymentGateway', '--module' => 'Shop'])->assertSuccessful();
        $this->artisan('make:trait', ['name' => 'HasCart', '--module' => 'Shop'])->assertSuccessful();

        $this->assertFileContains(base_path('Modules/Shop/Services/CheckoutService.php'), 'namespace Modules\Shop\Services;');
        $this->assertFileContains(base_path('Modules/Shop/Facades/Cart.php'), 'return \Modules\Shop\Services\CartService::class;');
        $this->assertFileContains(base_path('Modules/Shop/Enums/OrderStatus.php'), 'namespace Modules\Shop\Enums;');
        $this->assertFileContains(base_path('Modules/Shop/Interfaces/PaymentGatewayInterface.php'), 'namespace Modules\Shop\Interfaces;');
        $this->assertFileContains(base_path('Modules/Shop/Traits/HasCart.php'), 'namespace Modules\Shop\Traits;');
    }

    #[Test]
    public function it_supports_the_nwidart_app_folder_layout(): void
    {
        $this->makeModuleFolder('Crm', appFolder: true);

        $this->artisan('make:repository', ['name' => 'Lead', '--module' => 'Crm', '--bind' => true])->assertSuccessful();

        $this->assertFileContains(base_path('Modules/Crm/app/Repositories/LeadRepository.php'),
            'namespace Modules\Crm\Repositories;');
        $this->assertFileContains(base_path('Modules/Crm/app/Providers/RepositoryServiceProvider.php'),
            '\Modules\Crm\Repositories\LeadRepositoryInterface::class');
    }

    #[Test]
    public function make_module_honours_a_forced_app_folder(): void
    {
        config(['repo-facade-builder.modules.app_folder' => 'app']);

        $this->artisan('make:module', ['name' => 'Hr'])->assertSuccessful();

        $provider = base_path('Modules/Hr/app/Providers/HrServiceProvider.php');
        $this->assertFileContains($provider, 'namespace Modules\Hr\Providers;', "return __DIR__.'/../..'");
        $this->assertFileExists(base_path('Modules/Hr/routes/web.php'));
    }

    #[Test]
    public function module_paths_and_namespaces_are_configurable(): void
    {
        config([
            'repo-facade-builder.modules.path' => 'Modules/Domain',
            'repo-facade-builder.modules.namespace' => 'Domain',
        ]);
        $this->makeModuleFolder('Domain/Billing');

        $this->artisan('make:repository', ['name' => 'Invoice', '--module' => 'Billing'])->assertSuccessful();

        $this->assertFileContains(base_path('Modules/Domain/Billing/Repositories/InvoiceRepository.php'),
            'namespace Domain\Billing\Repositories;');
    }

    #[Test]
    public function a_generated_module_boots_and_resolves_its_bindings(): void
    {
        $this->artisan('make:module', ['name' => 'Inventory'])->assertSuccessful();
        $this->artisan('make:repository', [
            'name' => 'Stock', '--module' => 'Inventory', '--bind' => true, '--service' => true,
        ])->assertSuccessful();

        spl_autoload_register(function (string $class) {
            if (str_starts_with($class, 'Modules\\')) {
                $file = base_path(str_replace('\\', '/', $class).'.php');
                is_file($file) && require $file;
            }
        });

        $this->app->register(InventoryServiceProvider::class);

        $this->assertInstanceOf(
            StockRepository::class,
            $this->app->make(StockRepositoryInterface::class)
        );
        $this->assertInstanceOf(
            StockService::class,
            $this->app->make(StockService::class)
        );
        $this->assertSame('Inventory', config('inventory.name'));
    }

    #[Test]
    public function package_commands_take_precedence_over_framework_generators(): void
    {
        $commands = Artisan::all();

        foreach (['make:enum', 'make:interface', 'make:trait'] as $name) {
            $this->assertStringStartsWith('SilverProtoQoL\\RepoFacadeBuilder\\', get_class($commands[$name]));
        }
    }
}
