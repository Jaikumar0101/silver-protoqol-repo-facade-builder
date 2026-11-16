<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SilverProtoQoL\RepoFacadeBuilder\Support\BindingRegistrar;
use SilverProtoQoL\RepoFacadeBuilder\Tests\TestCase;

class EdgeCasesTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function names(): array
    {
        return [
            'kebab case' => ['user-profile', 'Repositories/UserProfileRepository.php'],
            'snake case' => ['user_profile', 'Repositories/UserProfileRepository.php'],
            'backslashes' => ['Billing\\Invoice', 'Repositories/Billing/InvoiceRepository.php'],
            'surrounding slashes' => ['/Billing/Invoice/', 'Repositories/Billing/InvoiceRepository.php'],
            'lowercase folders' => ['billing/invoice', 'Repositories/Billing/InvoiceRepository.php'],
            'double slashes' => ['Billing//Invoice', 'Repositories/Billing/InvoiceRepository.php'],
            'exact suffix name' => ['Repository', 'Repositories/RepositoryRepository.php'],
        ];
    }

    #[Test]
    #[DataProvider('names')]
    public function it_normalises_names(string $input, string $expected): void
    {
        $this->artisan('make:repository', ['name' => $input])->assertSuccessful();

        $this->assertFileExists(app_path($expected));
        $this->assertValidPhp(app_path($expected));
    }

    #[Test]
    public function suffixes_are_not_duplicated_for_any_generator(): void
    {
        $this->artisan('make:service', ['name' => 'PaymentService'])->assertSuccessful();
        $this->artisan('make:interface', ['name' => 'GatewayInterface'])->assertSuccessful();
        $this->artisan('make:facade', ['name' => 'CartFacade'])->assertSuccessful();

        $this->assertFileExists(app_path('Services/PaymentService.php'));
        $this->assertFileExists(app_path('Interfaces/GatewayInterface.php'));
        $this->assertFileExists(app_path('Facades/Cart.php'));
    }

    #[Test]
    public function a_facade_named_like_its_service_still_compiles(): void
    {
        $this->artisan('make:facade', ['name' => 'PaymentService', '--service' => true])->assertSuccessful();

        $this->assertFileContains(app_path('Facades/PaymentService.php'), 'class PaymentService extends Facade',
            'return \App\Services\PaymentService::class;');
        $this->assertValidPhp(app_path('Facades/PaymentService.php'));
    }

    #[Test]
    public function a_fully_qualified_existing_model_is_used_as_is(): void
    {
        $this->artisan('make:repository', ['name' => 'User', '--model' => 'Illuminate\Foundation\Auth\User'])
            ->doesntExpectOutputToContain('does not exist')
            ->assertSuccessful();

        $this->assertFileContains(app_path('Repositories/UserRepository.php'),
            'use Illuminate\Foundation\Auth\User;', 'protected User $model');
    }

    #[Test]
    public function a_missing_model_produces_a_warning(): void
    {
        $this->artisan('make:repository', ['name' => 'Ghost', '--model' => 'Ghost'])
            ->expectsOutputToContain('does not exist yet')
            ->assertSuccessful();
    }

    #[Test]
    public function the_repository_provider_is_registered_in_bootstrap_providers(): void
    {
        $this->artisan('make:repository', ['name' => 'User', '--bind' => true])->assertSuccessful();

        $this->assertFileContains(base_path('bootstrap/providers.php'), 'App\Providers\RepositoryServiceProvider::class');
    }

    #[Test]
    public function a_provider_without_the_marker_gets_a_manual_instruction(): void
    {
        @mkdir(app_path('Providers'), 0777, true);
        file_put_contents(app_path('Providers/RepositoryServiceProvider.php'), "<?php\n// customised, no marker\n");

        $this->artisan('make:repository', ['name' => 'User', '--bind' => true])
            ->expectsOutputToContain('Marker not found')
            ->assertSuccessful();

        $this->assertStringNotContainsString(BindingRegistrar::MARKER, file_get_contents(app_path('Providers/RepositoryServiceProvider.php')));
    }

    #[Test]
    public function the_binding_provider_name_is_configurable(): void
    {
        config(['repo-facade-builder.repository.provider' => 'DataServiceProvider']);

        $this->artisan('make:repository', ['name' => 'User', '--bind' => true])->assertSuccessful();

        $this->assertFileContains(app_path('Providers/DataServiceProvider.php'), 'class DataServiceProvider');
        @unlink(app_path('Providers/DataServiceProvider.php'));
    }

    #[Test]
    public function make_module_rejects_invalid_names(): void
    {
        $this->artisan('make:module', ['name' => 'Blog/Posts'])->assertFailed();
        $this->artisan('make:module', ['name' => '123'])->assertFailed();

        $this->assertDirectoryDoesNotExist(base_path('Modules'));
    }

    #[Test]
    public function make_module_can_skip_provider_registration(): void
    {
        $this->artisan('make:module', ['name' => 'Blog', '--no-register' => true])->assertSuccessful();

        $this->assertStringNotContainsString('BlogServiceProvider', file_get_contents(base_path('bootstrap/providers.php')));
    }

    #[Test]
    public function make_module_registers_its_provider_by_default(): void
    {
        $this->artisan('make:module', ['name' => 'Blog'])->assertSuccessful();

        $this->assertFileContains(base_path('bootstrap/providers.php'), 'Modules\Blog\Providers\BlogServiceProvider::class');
    }

    #[Test]
    public function make_module_hints_at_autoloading_without_touching_composer_json(): void
    {
        $before = file_get_contents(base_path('composer.json'));

        $this->artisan('make:module', ['name' => 'Blog'])
            ->expectsOutputToContain('autoload.psr-4')
            ->assertSuccessful();

        $this->assertSame($before, file_get_contents(base_path('composer.json')));
    }

    #[Test]
    public function make_module_autoload_adds_the_psr4_mapping_once(): void
    {
        $this->artisan('make:module', ['name' => 'Blog', '--autoload' => true])->assertSuccessful();
        $this->artisan('make:module', ['name' => 'Shop', '--autoload' => true])->assertSuccessful();

        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertSame('Modules/', $composer['autoload']['psr-4']['Modules\\']);
        $this->assertSame('app/', $composer['autoload']['psr-4']['App\\'], 'Existing mappings are preserved.');
    }

    #[Test]
    public function make_module_autoload_maps_each_module_for_the_app_folder_layout(): void
    {
        config(['repo-facade-builder.modules.app_folder' => 'app']);

        $this->artisan('make:module', ['name' => 'Blog', '--autoload' => true])->assertSuccessful();

        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $this->assertSame('Modules/Blog/app/', $composer['autoload']['psr-4']['Modules\\Blog\\']);
    }

    #[Test]
    public function config_and_stubs_can_be_published(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'repo-facade-builder-config'])->assertSuccessful();
        $this->artisan('vendor:publish', ['--tag' => 'repo-facade-builder-stubs'])->assertSuccessful();

        $this->assertFileExists(config_path('repo-facade-builder.php'));
        $this->assertFileExists(base_path('stubs/repo-facade-builder/repository.stub'));
        $this->assertFileExists(base_path('stubs/repo-facade-builder/module/provider.stub'));
    }

    #[Test]
    public function all_commands_are_registered(): void
    {
        $commands = array_keys(Artisan::all());

        foreach (['make:repository', 'make:repo', 'make:service', 'make:facade', 'make:enum',
            'make:interface', 'make:trait', 'make:module'] as $command) {
            $this->assertContains($command, $commands);
        }
    }
}
