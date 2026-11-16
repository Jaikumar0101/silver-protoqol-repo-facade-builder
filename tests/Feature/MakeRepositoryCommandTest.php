<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use SilverProtoQoL\RepoFacadeBuilder\Tests\TestCase;

class MakeRepositoryCommandTest extends TestCase
{
    #[Test]
    public function it_creates_a_repository_and_interface(): void
    {
        $this->artisan('make:repository', ['name' => 'User'])->assertSuccessful();

        $this->assertFileContains(app_path('Repositories/UserRepositoryInterface.php'),
            'namespace App\Repositories;', 'interface UserRepositoryInterface');
        $this->assertFileContains(app_path('Repositories/UserRepository.php'),
            'namespace App\Repositories;', 'class UserRepository implements UserRepositoryInterface');
        $this->assertValidPhp(app_path('Repositories/UserRepository.php'));
    }

    #[Test]
    public function the_repo_alias_works_and_suffix_is_not_duplicated(): void
    {
        $this->artisan('make:repo', ['name' => 'OrderRepository'])->assertSuccessful();

        $this->assertFileExists(app_path('Repositories/OrderRepository.php'));
        $this->assertFileDoesNotExist(app_path('Repositories/OrderRepositoryRepository.php'));
    }

    #[Test]
    public function it_supports_nested_folders(): void
    {
        $this->artisan('make:repository', ['name' => 'Accounting/Bill/CreditNote'])->assertSuccessful();

        $this->assertFileContains(app_path('Repositories/Accounting/Bill/CreditNoteRepository.php'),
            'namespace App\Repositories\Accounting\Bill;');
    }

    #[Test]
    public function it_generates_crud_methods_for_a_model(): void
    {
        $this->artisan('make:repository', ['name' => 'User', '--model' => 'User'])->assertSuccessful();

        $class = app_path('Repositories/UserRepository.php');
        $this->assertFileContains($class,
            'use App\Models\User;',
            'use Illuminate\Database\Eloquent\Collection;',
            'protected User $model',
            'public function paginate(int $perPage = 15',
            'public function delete(int|string $id): bool');
        $this->assertFileContains(app_path('Repositories/UserRepositoryInterface.php'),
            'public function findOrFail(int|string $id, array $columns = [\'*\']): User;');
        $this->assertValidPhp($class);
        $this->assertValidPhp(app_path('Repositories/UserRepositoryInterface.php'));
    }

    #[Test]
    public function it_can_place_interfaces_in_a_contracts_folder(): void
    {
        config(['repo-facade-builder.repository.interface_folder' => 'Contracts']);

        $this->artisan('make:repository', ['name' => 'Billing/Invoice'])->assertSuccessful();

        $this->assertFileContains(app_path('Repositories/Contracts/Billing/InvoiceRepositoryInterface.php'),
            'namespace App\Repositories\Contracts\Billing;');
        $this->assertFileContains(app_path('Repositories/Billing/InvoiceRepository.php'),
            'use App\Repositories\Contracts\Billing\InvoiceRepositoryInterface;');
    }

    #[Test]
    public function it_does_not_overwrite_without_force(): void
    {
        $this->artisan('make:repository', ['name' => 'User'])->assertSuccessful();
        file_put_contents(app_path('Repositories/UserRepository.php'), 'custom');

        $this->artisan('make:repository', ['name' => 'User'])->assertSuccessful();
        $this->assertSame('custom', file_get_contents(app_path('Repositories/UserRepository.php')));

        $this->artisan('make:repository', ['name' => 'User', '--force' => true])->assertSuccessful();
        $this->assertStringContainsString('class UserRepository', file_get_contents(app_path('Repositories/UserRepository.php')));
    }

    #[Test]
    public function it_registers_bindings_in_a_repository_service_provider(): void
    {
        $this->artisan('make:repository', ['name' => 'User', '--bind' => true])->assertSuccessful();
        $this->artisan('make:repository', ['name' => 'Billing/Invoice', '--bind' => true])->assertSuccessful();
        $this->artisan('make:repository', ['name' => 'User', '--bind' => true, '--force' => true])->assertSuccessful();

        $provider = app_path('Providers/RepositoryServiceProvider.php');
        $this->assertFileContains($provider,
            '\App\Repositories\UserRepositoryInterface::class => \App\Repositories\UserRepository::class,',
            '\App\Repositories\Billing\InvoiceRepositoryInterface::class => \App\Repositories\Billing\InvoiceRepository::class,');
        $this->assertSame(1, substr_count(file_get_contents($provider), 'UserRepositoryInterface::class =>'));
        $this->assertValidPhp($provider);
    }

    #[Test]
    public function it_can_also_generate_a_service(): void
    {
        $this->artisan('make:repository', ['name' => 'User', '--service' => true])->assertSuccessful();

        $this->assertFileContains(app_path('Services/UserService.php'),
            'use App\Repositories\UserRepositoryInterface;',
            'protected UserRepositoryInterface $repository');
    }
}
