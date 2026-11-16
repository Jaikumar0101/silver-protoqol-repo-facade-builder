<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SilverProtoQoL\RepoFacadeBuilder\Support\BindingRegistrar;

class BindingRegistrarTest extends TestCase
{
    private string $path;

    private BindingRegistrar $registrar;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'rfb').'.php';
        $this->registrar = new BindingRegistrar(new Filesystem);
        @unlink(substr($this->path, 0, -4));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    private function provider(string $body): void
    {
        file_put_contents($this->path, "<?php\n\nclass P\n{\n    public \$bindings = [\n{$body}    ];\n}\n");
    }

    #[Test]
    public function it_inserts_bindings_above_the_marker_with_matching_indentation(): void
    {
        $this->provider('        '.BindingRegistrar::MARKER."\n");

        $this->assertSame(BindingRegistrar::ADDED, $this->registrar->add($this->path, 'App\A', 'App\B'));
        $this->assertSame(BindingRegistrar::ADDED, $this->registrar->add($this->path, '\App\C', '\App\D'));

        $this->assertStringContainsString(
            "        \\App\\A::class => \\App\\B::class,\n        \\App\\C::class => \\App\\D::class,\n        ".BindingRegistrar::MARKER,
            file_get_contents($this->path)
        );
    }

    #[Test]
    public function it_does_not_duplicate_an_existing_binding(): void
    {
        $this->provider('        '.BindingRegistrar::MARKER."\n");
        $this->registrar->add($this->path, 'App\A', 'App\B');

        $this->assertSame(BindingRegistrar::EXISTS, $this->registrar->add($this->path, 'App\A', 'App\Other'));
        $this->assertSame(1, substr_count(file_get_contents($this->path), '\App\A::class'));
    }

    #[Test]
    public function it_reports_a_missing_marker_without_touching_the_file(): void
    {
        $this->provider("        // nothing here\n");
        $before = file_get_contents($this->path);

        $this->assertSame(BindingRegistrar::NO_MARKER, $this->registrar->add($this->path, 'App\A', 'App\B'));
        $this->assertSame($before, file_get_contents($this->path));
    }

    #[Test]
    public function it_handles_namespaces_containing_regex_and_replacement_characters(): void
    {
        $this->provider('    '.BindingRegistrar::MARKER."\n");

        $this->registrar->add($this->path, 'Modules\Blog\Repositories\PostRepositoryInterface', 'Modules\Blog\Repositories\PostRepository');

        $this->assertStringContainsString(
            '\Modules\Blog\Repositories\PostRepositoryInterface::class => \Modules\Blog\Repositories\PostRepository::class,',
            file_get_contents($this->path)
        );
        exec('php -l '.escapeshellarg($this->path), $out, $code);
        $this->assertSame(0, $code);
    }

    #[Test]
    public function it_formats_a_binding_line(): void
    {
        $this->assertSame('\A\B::class => \C\D::class,', BindingRegistrar::line('A\B', '\C\D'));
    }
}
