<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use SilverProtoQoL\RepoFacadeBuilder\Support\ModuleResolver;
use SilverProtoQoL\RepoFacadeBuilder\Support\StubRenderer;

/**
 * Shared plumbing for every generator: name parsing, app/module context,
 * path + namespace resolution, stub rendering and safe file writing.
 */
abstract class GeneratorCommand extends Command
{
    /**
     * Options appended to every generator signature.
     */
    protected const COMMON_OPTIONS = '
        {--m|module= : Generate inside the given module (e.g. Blog)}
        {--f|force : Overwrite files that already exist}';

    /**
     * The resolved module name, or null when generating into app/.
     */
    protected ?string $module = null;

    public function __construct(
        protected Filesystem $files,
        protected ModuleResolver $modules,
        protected StubRenderer $stubs,
    ) {
        $this->signature = rtrim($this->signature).static::COMMON_OPTIONS;

        parent::__construct();
    }

    /**
     * Resolve the --module option. Returns false (after printing an error)
     * when the module does not exist.
     */
    protected function resolveContext(): bool
    {
        $module = $this->option('module');

        if ($module === null || $module === '') {
            $this->module = null;

            return true;
        }

        if (! $this->modules->exists($module)) {
            $this->components->error(sprintf(
                'Module [%s] does not exist at [%s]. Create it with: php artisan make:module %s',
                $this->modules->name($module),
                $this->relativePath($this->modules->path($module)),
                $this->modules->name($module),
            ));

            return false;
        }

        $this->module = $this->modules->name($module);

        return true;
    }

    /**
     * Split "Accounting/Bill/CreditNote" (or back-slashed) into sub-folders and
     * a StudlyCase class name, removing a duplicated suffix ("UserRepository").
     *
     * @return array{0: array<int, string>, 1: string}
     */
    protected function parseName(string $input, string $suffix = ''): array
    {
        $input = str_replace('\\', '/', trim($input, " /\\"));

        $parts = array_values(array_map(
            fn (string $part) => Str::studly($part),
            array_filter(explode('/', $input), fn (string $part) => $part !== '')
        ));

        $class = (string) array_pop($parts);

        if ($suffix !== '' && $class !== $suffix && str_ends_with($class, $suffix)) {
            $class = substr($class, 0, -strlen($suffix));
        }

        return [$parts, $class];
    }

    /**
     * Base directory classes are generated into (app/ or the module root).
     */
    protected function basePath(): string
    {
        return $this->module
            ? $this->modules->appPath($this->module)
            : rtrim($this->laravel->path(), '/\\');
    }

    /**
     * Root namespace ("App" or "Modules\Blog").
     */
    protected function baseNamespace(): string
    {
        return $this->module
            ? $this->modules->namespace($this->module)
            : trim($this->laravel->getNamespace(), '\\');
    }

    /**
     * Configured folder for a generator type, e.g. "Repositories".
     */
    protected function folder(string $type): string
    {
        return trim((string) config("repo-facade-builder.paths.{$type}", Str::studly(Str::plural($type))), '/\\');
    }

    /**
     * Directory + namespace for a generator type and its sub-folders.
     *
     * @param  array<int, string>  $segments
     * @return array{0: string, 1: string}
     */
    protected function location(string $type, array $segments = [], ?string $extraFolder = null): array
    {
        $parts = array_values(array_filter(
            [...explode('/', str_replace('\\', '/', $this->folder($type))), ...($extraFolder ? explode('/', $extraFolder) : []), ...$segments],
            fn ($part) => $part !== ''
        ));

        return [
            $this->basePath().DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $parts),
            $this->baseNamespace().'\\'.implode('\\', $parts),
        ];
    }

    /**
     * Render a stub and write it, honouring --force. Returns true when written.
     *
     * @param  array<string, string>  $replacements
     */
    protected function writeStub(string $path, string $stub, array $replacements): bool
    {
        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->warn(sprintf('File [%s] already exists. Use --force to overwrite.', $this->relativePath($path)));

            return false;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $this->stubs->render($stub, $replacements));

        $this->components->info(sprintf('Created [%s].', $this->relativePath($path)));

        return true;
    }

    /**
     * Build a `use` block. Imports in the current namespace are skipped.
     *
     * @param  array<int, string|null>  $classes
     */
    protected function imports(string $namespace, array $classes): string
    {
        $uses = collect($classes)
            ->filter()
            ->map(fn (string $class) => ltrim($class, '\\'))
            ->reject(fn (string $class) => Str::beforeLast($class, '\\') === $namespace)
            ->unique()
            ->sort()
            ->map(fn (string $class) => "use {$class};")
            ->implode("\n");

        return $uses === '' ? '' : "\n{$uses}\n";
    }

    /**
     * Arguments forwarded when a generator calls another generator.
     *
     * @return array<string, mixed>
     */
    protected function forwardedOptions(): array
    {
        return array_filter([
            '--module' => $this->module,
            '--force' => (bool) $this->option('force'),
        ]);
    }

    protected function relativePath(string $path): string
    {
        $base = rtrim($this->laravel->basePath(), '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    protected function contextLabel(): string
    {
        return $this->module ? "module [{$this->module}]" : 'app';
    }
}
