<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use SilverProtoQoL\RepoFacadeBuilder\Support\BindingRegistrar;
use SilverProtoQoL\RepoFacadeBuilder\Support\ModuleResolver;
use SilverProtoQoL\RepoFacadeBuilder\Support\StubRenderer;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : Module name (e.g. Blog)}
        {--a|autoload : Add the modules PSR-4 mapping to composer.json if it is missing}
        {--no-register : Do not register the module service provider in bootstrap/providers.php}';

    protected $description = 'Scaffold a new application module with its service providers';

    public function __construct(
        protected Filesystem $files,
        protected ModuleResolver $modules,
        protected StubRenderer $stubs,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = Str::studly(trim($this->argument('name'), '/\\ '));

        if ($module === '' || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $module)) {
            $this->components->error('Module name must be a single StudlyCase word (e.g. Blog).');

            return self::FAILURE;
        }

        if ($this->modules->exists($module)) {
            $this->components->error("Module [{$this->modules->name($module)}] already exists.");

            return self::FAILURE;
        }

        $root = $this->modules->path($module);
        $this->files->ensureDirectoryExists($root);

        // A brand-new module has no app/ folder yet, so only an explicit config forces one.
        $configured = config('repo-facade-builder.modules.app_folder', 'auto');
        $appFolder = $configured === 'auto' || $configured === null ? '' : trim((string) $configured, '/\\');
        $appPath = $root.($appFolder !== '' ? DIRECTORY_SEPARATOR.$appFolder : '');
        $namespace = $this->modules->namespace($module);
        $providerFolder = trim((string) config('repo-facade-builder.paths.provider', 'Providers'), '/\\');

        foreach ((array) config('repo-facade-builder.modules.scaffold', []) as $folder) {
            $this->files->ensureDirectoryExists($appPath.DIRECTORY_SEPARATOR.trim($folder, '/\\'));
            $this->files->put($appPath.DIRECTORY_SEPARATOR.trim($folder, '/\\').DIRECTORY_SEPARATOR.'.gitkeep', '');
        }

        foreach (['routes', 'database/migrations', 'resources/views', 'config'] as $folder) {
            $this->files->ensureDirectoryExists($root.DIRECTORY_SEPARATOR.$folder);
        }

        $this->files->put($root.'/database/migrations/.gitkeep', '');
        $this->files->put($root.'/resources/views/.gitkeep', '');

        $providerNamespace = $namespace.'\\'.str_replace('/', '\\', $providerFolder);
        $providerPath = $appPath.DIRECTORY_SEPARATOR.$providerFolder;
        $repositoryProvider = (string) config('repo-facade-builder.repository.provider', 'RepositoryServiceProvider');
        $depth = substr_count($providerFolder, '/') + 1 + ($appFolder !== '' ? substr_count($appFolder, '/') + 1 : 0);

        $replacements = [
            'module' => $module,
            'moduleKebab' => Str::kebab($module),
            'moduleSnake' => Str::snake($module),
            'namespace' => $providerNamespace,
            'rootNamespace' => $namespace,
            'repositoryProvider' => $repositoryProvider,
            'moduleRoot' => '__DIR__.\''.str_repeat('/..', $depth).'\'',
            'marker' => BindingRegistrar::MARKER,
            'class' => $repositoryProvider,
        ];

        $files = [
            "{$providerPath}/{$module}ServiceProvider.php" => 'module/provider',
            "{$providerPath}/{$repositoryProvider}.php" => 'provider.repository',
            "{$root}/routes/web.php" => 'module/routes.web',
            "{$root}/routes/api.php" => 'module/routes.api',
            "{$root}/config/config.php" => 'module/config',
        ];

        foreach ($files as $path => $stub) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $this->stubs->render($stub, $replacements));
        }

        $this->components->info("Module [{$module}] created at [{$this->relativePath($root)}].");

        $this->registerProvider("{$providerNamespace}\\{$module}ServiceProvider");
        $this->ensureAutoload($module, $appFolder);

        $this->components->bulletList([
            "php artisan make:repository Post --module={$module} --model=Post --bind --service",
            "php artisan make:facade Blogger --module={$module} --service",
        ]);

        return self::SUCCESS;
    }

    protected function registerProvider(string $provider): void
    {
        if ($this->option('no-register')) {
            return;
        }

        if (method_exists(ServiceProvider::class, 'addProviderToBootstrapFile')
            && ServiceProvider::addProviderToBootstrapFile($provider)) {
            $this->components->info("Registered [{$provider}] in bootstrap/providers.php.");

            return;
        }

        $this->components->warn("Register [{$provider}] in the providers array of config/app.php.");
    }

    /**
     * Make sure Composer can autoload the modules namespace.
     */
    protected function ensureAutoload(string $module, string $appFolder): void
    {
        $composerPath = $this->laravel->basePath('composer.json');

        if (! $this->files->exists($composerPath)) {
            return;
        }

        $composer = json_decode($this->files->get($composerPath), true);

        if (! is_array($composer)) {
            return;
        }

        // Classes nested in app/ can't share one root mapping, so map each module.
        $namespace = ($appFolder !== '' ? $this->modules->namespace($module) : $this->modules->rootNamespace()).'\\';
        $root = $appFolder !== '' ? $this->modules->path($module).'/'.$appFolder : $this->modules->rootPath();
        $path = str_replace('\\', '/', $this->relativePath($root)).'/';
        $psr4 = $composer['autoload']['psr-4'] ?? [];

        if (array_key_exists($namespace, $psr4) || class_exists(\Nwidart\Modules\LaravelModulesServiceProvider::class)) {
            return;
        }

        if (! $this->option('autoload')) {
            $this->components->warn(sprintf(
                'Add "%s": "%s" to autoload.psr-4 in composer.json (or re-run with --autoload), then run composer dump-autoload.',
                addslashes($namespace),
                $path
            ));

            return;
        }

        $composer['autoload']['psr-4'][$namespace] = $path;

        $this->files->put(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
        );

        $this->components->info("Added [{$namespace}] => [{$path}] to composer.json. Run composer dump-autoload.");
    }

    protected function relativePath(string $path): string
    {
        $base = rtrim($this->laravel->basePath(), '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
