<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SilverProtoQoL\RepoFacadeBuilder\Support\StubRenderer;
use SilverProtoQoL\RepoFacadeBuilder\Tests\TestCase;

class StubRendererTest extends TestCase
{
    #[Test]
    public function it_replaces_spaced_and_compact_placeholders(): void
    {
        $dir = base_path('stubs/repo-facade-builder');
        @mkdir($dir, 0777, true);
        file_put_contents("{$dir}/trait.stub", '{{ class }}|{{class}}|{{ unknown }}');

        $this->assertSame('Foo|Foo|{{ unknown }}', $this->app->make(StubRenderer::class)->render('trait', ['class' => 'Foo']));
    }

    #[Test]
    public function it_falls_back_to_package_stubs(): void
    {
        $this->assertSame(
            StubRenderer::defaultPath().'/enum.stub',
            $this->app->make(StubRenderer::class)->path('enum')
        );
    }

    #[Test]
    public function every_shipped_stub_renders_to_valid_php(): void
    {
        $renderer = $this->app->make(StubRenderer::class);
        $values = [
            'namespace' => 'App\Example', 'rootNamespace' => 'App', 'class' => 'Example',
            'interface' => 'ExampleInterface', 'model' => 'Example', 'repository' => 'ExampleInterface',
            'imports' => '', 'accessor' => "'example'", 'see' => '', 'marker' => '//',
            'module' => 'Blog', 'moduleKebab' => 'blog', 'moduleSnake' => 'blog',
            'repositoryProvider' => 'RepositoryServiceProvider', 'moduleRoot' => "__DIR__.'/..'",
        ];

        $stubs = glob(StubRenderer::defaultPath().'/{,module/}*.stub', GLOB_BRACE);
        $this->assertNotEmpty($stubs);

        foreach ($stubs as $stub) {
            $name = substr($stub, strlen(StubRenderer::defaultPath()) + 1, -5);
            $contents = $renderer->render($name, $values);

            $this->assertStringNotContainsString('{{', $contents, "Unreplaced placeholder in [{$name}].");

            $file = tempnam(sys_get_temp_dir(), 'stub');
            file_put_contents($file, $contents);
            exec('php -l '.escapeshellarg($file).' 2>&1', $out, $code);
            unlink($file);

            $this->assertSame(0, $code, "Stub [{$name}] renders invalid PHP.");
        }
    }
}
