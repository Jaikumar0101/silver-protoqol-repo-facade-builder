<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use SilverProtoQoL\RepoFacadeBuilder\Tests\TestCase;

class GeneratorCommandsTest extends TestCase
{
    #[Test]
    public function it_creates_a_service(): void
    {
        $this->artisan('make:service', ['name' => 'Payment/Stripe'])->assertSuccessful();

        $this->assertFileContains(app_path('Services/Payment/StripeService.php'),
            'namespace App\Services\Payment;', 'class StripeService');
    }

    #[Test]
    public function a_service_can_inject_a_repository_by_short_name(): void
    {
        $this->artisan('make:service', ['name' => 'Order', '--repository' => 'Sales/Order'])->assertSuccessful();

        $this->assertFileContains(app_path('Services/OrderService.php'),
            'use App\Repositories\Sales\OrderRepositoryInterface;',
            'protected OrderRepositoryInterface $repository');
    }

    #[Test]
    public function it_creates_a_facade_with_a_snake_case_accessor(): void
    {
        $this->artisan('make:facade', ['name' => 'Billing/PaymentGateway'])->assertSuccessful();

        $path = app_path('Facades/Billing/PaymentGateway.php');
        $this->assertFileContains($path,
            'namespace App\Facades\Billing;',
            'use Illuminate\Support\Facades\Facade;',
            "return 'payment_gateway';");
        $this->assertValidPhp($path);
    }

    #[Test]
    public function a_facade_can_generate_and_point_to_its_service(): void
    {
        $this->artisan('make:facade', ['name' => 'Payment', '--service' => true])->assertSuccessful();

        $this->assertFileContains(app_path('Facades/Payment.php'),
            'return \App\Services\PaymentService::class;', '@see \App\Services\PaymentService');
        $this->assertFileExists(app_path('Services/PaymentService.php'));
        $this->assertValidPhp(app_path('Facades/Payment.php'));
    }

    #[Test]
    public function a_facade_accepts_a_custom_accessor(): void
    {
        $this->artisan('make:facade', ['name' => 'Cart', '--accessor' => 'shop.cart'])->assertSuccessful();

        $this->assertFileContains(app_path('Facades/Cart.php'), "return 'shop.cart';");
    }

    #[Test]
    public function it_creates_enums_with_optional_backing_types(): void
    {
        $this->artisan('make:enum', ['name' => 'Status'])->assertSuccessful();
        $this->artisan('make:enum', ['name' => 'Orders/OrderStatus', '--type' => 'string'])->assertSuccessful();
        $this->artisan('make:enum', ['name' => 'Priority', '--type' => 'int'])->assertSuccessful();

        $this->assertFileContains(app_path('Enums/Status.php'), 'enum Status'.PHP_EOL);
        $this->assertFileContains(app_path('Enums/Orders/OrderStatus.php'),
            'namespace App\Enums\Orders;', 'enum OrderStatus: string');
        $this->assertFileContains(app_path('Enums/Priority.php'), 'enum Priority: int');
    }

    #[Test]
    public function it_rejects_invalid_enum_types(): void
    {
        $this->artisan('make:enum', ['name' => 'Status', '--type' => 'float'])->assertFailed();

        $this->assertFileDoesNotExist(app_path('Enums/Status.php'));
    }

    #[Test]
    public function it_creates_interfaces_and_traits(): void
    {
        $this->artisan('make:interface', ['name' => 'Contracts/Payment/Gateway'])->assertSuccessful();
        $this->artisan('make:trait', ['name' => 'Concerns/HasUuid'])->assertSuccessful();

        $this->assertFileContains(app_path('Interfaces/Contracts/Payment/GatewayInterface.php'),
            'namespace App\Interfaces\Contracts\Payment;', 'interface GatewayInterface');
        $this->assertFileContains(app_path('Traits/Concerns/HasUuid.php'),
            'namespace App\Traits\Concerns;', 'trait HasUuid');
    }

    #[Test]
    public function published_stubs_take_precedence(): void
    {
        $dir = base_path('stubs/repo-facade-builder');
        @mkdir($dir, 0777, true);
        file_put_contents("{$dir}/trait.stub", "<?php\n\nnamespace {{ namespace }};\n\n// custom\ntrait {{ class }} {}\n");

        $this->artisan('make:trait', ['name' => 'HasSlug'])->assertSuccessful();

        $this->assertFileContains(app_path('Traits/HasSlug.php'), '// custom', 'trait HasSlug {}');
    }

    #[Test]
    public function folders_are_configurable(): void
    {
        config(['repo-facade-builder.paths.interface' => 'Contracts']);

        $this->artisan('make:interface', ['name' => 'Notifier'])->assertSuccessful();

        $this->assertFileContains(app_path('Contracts/NotifierInterface.php'), 'namespace App\Contracts;');

        @unlink(app_path('Contracts/NotifierInterface.php'));
        @rmdir(app_path('Contracts'));
    }
}
