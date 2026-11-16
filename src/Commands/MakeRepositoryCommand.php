<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use SilverProtoQoL\RepoFacadeBuilder\Support\BindingRegistrar;
use SilverProtoQoL\RepoFacadeBuilder\Support\ModuleResolver;
use SilverProtoQoL\RepoFacadeBuilder\Support\StubRenderer;

class MakeRepositoryCommand extends GeneratorCommand
{
    protected $signature = 'make:repository
        {name : Repository name, sub-folders allowed (e.g. Accounting/Bill/CreditNote)}
        {--model= : Eloquent model to wire in; generates CRUD methods (e.g. User or App\Models\User)}
        {--b|bind : Register the interface => repository binding in a RepositoryServiceProvider}
        {--s|service : Also create a service that receives the repository interface}';

    protected $description = 'Create a new repository class and its interface';

    protected $aliases = ['make:repo'];

    public function __construct(
        Filesystem $files,
        ModuleResolver $modules,
        StubRenderer $stubs,
        protected BindingRegistrar $bindings,
    ) {
        parent::__construct($files, $modules, $stubs);
    }

    public function handle(): int
    {
        if (! $this->resolveContext()) {
            return self::FAILURE;
        }

        [$segments, $name] = $this->parseName($this->argument('name'), 'Repository');

        [$directory, $namespace] = $this->location('repository', $segments);
        [$interfaceDirectory, $interfaceNamespace] = $this->location(
            'repository',
            $segments,
            config('repo-facade-builder.repository.interface_folder') ?: null
        );

        $class = "{$name}Repository";
        $interface = "{$name}RepositoryInterface";
        $classFqcn = "{$namespace}\\{$class}";
        $interfaceFqcn = "{$interfaceNamespace}\\{$interface}";

        $model = $this->resolveModel();

        if ($model && ! class_exists($model)) {
            $this->components->warn("Model [{$model}] does not exist yet.");
        }

        $modelClass = $model ? class_basename($model) : '';

        $this->writeStub(
            "{$interfaceDirectory}/{$interface}.php",
            $model ? 'repository.interface.model' : 'repository.interface',
            [
                'namespace' => $interfaceNamespace,
                'class' => $interface,
                'model' => $modelClass,
                'imports' => $this->imports($interfaceNamespace, $model ? [
                    $model,
                    'Illuminate\Contracts\Pagination\LengthAwarePaginator',
                    'Illuminate\Database\Eloquent\Collection',
                ] : []),
            ]
        );

        $this->writeStub(
            "{$directory}/{$class}.php",
            $model ? 'repository.model' : 'repository',
            [
                'namespace' => $namespace,
                'class' => $class,
                'interface' => $interface,
                'model' => $modelClass,
                'imports' => $this->imports($namespace, $model ? [
                    $interfaceFqcn,
                    $model,
                    'Illuminate\Contracts\Pagination\LengthAwarePaginator',
                    'Illuminate\Database\Eloquent\Collection',
                ] : [$interfaceFqcn]),
            ]
        );

        if ($this->option('bind')) {
            $this->registerBinding($interfaceFqcn, $classFqcn);
        }

        if ($this->option('service')) {
            $this->call('make:service', [
                'name' => $this->argument('name'),
                '--repository' => $interfaceFqcn,
                ...$this->forwardedOptions(),
            ]);
        }

        return self::SUCCESS;
    }

    protected function resolveModel(): ?string
    {
        $model = $this->option('model');

        if (! $model) {
            return null;
        }

        $model = str_replace('/', '\\', trim($model, '/\\'));

        if (str_contains($model, '\\') && class_exists($model)) {
            return $model;
        }

        // Relative names resolve against the current context's Models folder.
        [, $modelNamespace] = $this->location('model');

        return "{$modelNamespace}\\{$model}";
    }

    protected function registerBinding(string $interface, string $concrete): void
    {
        $providerClass = (string) config('repo-facade-builder.repository.provider', 'RepositoryServiceProvider');
        [$providerDirectory, $providerNamespace] = $this->location('provider');
        $providerPath = "{$providerDirectory}/{$providerClass}.php";
        $providerFqcn = "{$providerNamespace}\\{$providerClass}";

        if (! $this->files->exists($providerPath)) {
            $this->files->ensureDirectoryExists($providerDirectory);
            $this->files->put($providerPath, $this->stubs->render('provider.repository', [
                'namespace' => $providerNamespace,
                'class' => $providerClass,
                'marker' => BindingRegistrar::MARKER,
            ]));

            $this->components->info(sprintf('Created [%s].', $this->relativePath($providerPath)));
            $this->registerProvider($providerFqcn);
        }

        $line = BindingRegistrar::line($interface, $concrete);

        match ($this->bindings->add($providerPath, $interface, $concrete)) {
            BindingRegistrar::ADDED => $this->components->info("Bound [{$interface}] in [{$providerClass}]."),
            BindingRegistrar::EXISTS => $this->components->warn("Binding for [{$interface}] already exists in [{$providerClass}]."),
            default => $this->components->warn("Marker not found in [{$this->relativePath($providerPath)}]. Add this to its \$bindings array manually:\n  {$line}"),
        };
    }

    /**
     * Register a freshly created provider where Laravel can find it.
     */
    protected function registerProvider(string $provider): void
    {
        if ($this->module) {
            $this->components->bulletList([
                "Register [{$provider}] from the module's service provider, e.g. \$this->app->register(\\{$provider}::class);",
                '(Modules created with make:module already do this.)',
            ]);

            return;
        }

        if (method_exists(ServiceProvider::class, 'addProviderToBootstrapFile')
            && ServiceProvider::addProviderToBootstrapFile($provider)) {
            $this->components->info("Registered [{$provider}] in bootstrap/providers.php.");

            return;
        }

        $this->components->warn("Register [{$provider}] in the providers array of config/app.php.");
    }
}
