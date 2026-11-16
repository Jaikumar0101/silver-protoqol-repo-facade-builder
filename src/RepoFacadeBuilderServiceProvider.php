<?php

namespace SilverProtoQoL\RepoFacadeBuilder;

use Illuminate\Support\ServiceProvider;
use SilverProtoQoL\RepoFacadeBuilder\Commands\MakeEnumCommand;
use SilverProtoQoL\RepoFacadeBuilder\Commands\MakeFacadeCommand;
use SilverProtoQoL\RepoFacadeBuilder\Commands\MakeInterfaceCommand;
use SilverProtoQoL\RepoFacadeBuilder\Commands\MakeModuleCommand;
use SilverProtoQoL\RepoFacadeBuilder\Commands\MakeRepositoryCommand;
use SilverProtoQoL\RepoFacadeBuilder\Commands\MakeServiceCommand;
use SilverProtoQoL\RepoFacadeBuilder\Commands\MakeTraitCommand;
use SilverProtoQoL\RepoFacadeBuilder\Support\BindingRegistrar;
use SilverProtoQoL\RepoFacadeBuilder\Support\ModuleResolver;
use SilverProtoQoL\RepoFacadeBuilder\Support\StubRenderer;

class RepoFacadeBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/repo-facade-builder.php', 'repo-facade-builder');

        $this->app->singleton(ModuleResolver::class);
        $this->app->singleton(StubRenderer::class);
        $this->app->singleton(BindingRegistrar::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/repo-facade-builder.php' => config_path('repo-facade-builder.php'),
        ], 'repo-facade-builder-config');

        $this->publishes([
            StubRenderer::defaultPath() => base_path('stubs/repo-facade-builder'),
        ], 'repo-facade-builder-stubs');

        $this->commands([
            MakeRepositoryCommand::class,
            MakeServiceCommand::class,
            MakeFacadeCommand::class,
            MakeEnumCommand::class,
            MakeInterfaceCommand::class,
            MakeTraitCommand::class,
            MakeModuleCommand::class,
        ]);
    }
}
