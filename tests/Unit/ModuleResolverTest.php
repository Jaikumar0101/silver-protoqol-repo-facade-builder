<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SilverProtoQoL\RepoFacadeBuilder\Support\ModuleResolver;
use SilverProtoQoL\RepoFacadeBuilder\Tests\TestCase;

class ModuleResolverTest extends TestCase
{
    private function resolver(): ModuleResolver
    {
        return $this->app->make(ModuleResolver::class);
    }

    #[Test]
    public function it_resolves_default_paths_and_namespaces(): void
    {
        $this->makeModuleFolder('Blog');
        $resolver = $this->resolver();

        $this->assertSame(base_path('Modules'), $resolver->rootPath());
        $this->assertSame('Modules', $resolver->rootNamespace());
        $this->assertSame(base_path('Modules').DIRECTORY_SEPARATOR.'Blog', $resolver->path('Blog'));
        $this->assertSame($resolver->path('Blog'), $resolver->appPath('Blog'));
        $this->assertSame('Modules\Blog', $resolver->namespace('Blog'));
        $this->assertTrue($resolver->exists('Blog'));
        $this->assertFalse($resolver->exists('Shop'));
    }

    #[Test]
    public function it_matches_existing_modules_case_insensitively(): void
    {
        $this->makeModuleFolder('UserManagement');

        $this->assertSame('UserManagement', $this->resolver()->name('usermanagement'));
        $this->assertSame('UserManagement', $this->resolver()->name('user-management'));
        $this->assertSame('NewModule', $this->resolver()->name('new_module'));
    }

    #[Test]
    public function it_detects_the_app_folder_layout(): void
    {
        $this->makeModuleFolder('Crm', appFolder: true);

        $this->assertSame('app', $this->resolver()->appFolder('Crm'));
        $this->assertSame($this->resolver()->path('Crm').DIRECTORY_SEPARATOR.'app', $this->resolver()->appPath('Crm'));
    }

    #[Test]
    public function the_app_folder_can_be_forced_either_way(): void
    {
        $this->makeModuleFolder('Crm', appFolder: true);
        $this->makeModuleFolder('Blog');

        config(['repo-facade-builder.modules.app_folder' => '']);
        $this->assertSame('', $this->resolver()->appFolder('Crm'));

        config(['repo-facade-builder.modules.app_folder' => 'src/']);
        $this->assertSame('src', $this->resolver()->appFolder('Blog'));
    }

    #[Test]
    public function it_lists_modules_sorted_and_handles_a_missing_root(): void
    {
        $this->assertSame([], $this->resolver()->all());

        $this->makeModuleFolder('Shop');
        $this->makeModuleFolder('Blog');

        $this->assertSame(['Blog', 'Shop'], $this->resolver()->all());
    }

    #[Test]
    public function it_accepts_an_absolute_root_path_and_custom_namespace(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rfb-modules-'.uniqid();
        config([
            'repo-facade-builder.modules.path' => $root,
            'repo-facade-builder.modules.namespace' => '\Domain\\',
        ]);

        $this->assertSame($root, $this->resolver()->rootPath());
        $this->assertSame('Domain\Billing', $this->resolver()->namespace('Billing'));
    }
}
