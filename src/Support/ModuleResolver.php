<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Resolves paths and namespaces for modules in a modular Laravel application.
 *
 * Works with a plain `Modules/{Name}` layout as well as nwidart/laravel-modules
 * (both the legacy layout and the v11+ layout that nests classes in `app/`).
 */
class ModuleResolver
{
    public function __construct(
        protected Application $app,
        protected Filesystem $files,
    ) {
    }

    public function rootPath(): string
    {
        $path = (string) $this->config('path', 'Modules');

        return $this->isAbsolute($path) ? rtrim($path, '/\\') : $this->app->basePath($path);
    }

    public function rootNamespace(): string
    {
        return trim((string) $this->config('namespace', 'Modules'), '\\');
    }

    /**
     * Normalise a module name, matching an existing folder case-insensitively.
     */
    public function name(string $module): string
    {
        $module = trim($module, '/\\ ');

        foreach ($this->all() as $existing) {
            if (strcasecmp($existing, $module) === 0) {
                return $existing;
            }
        }

        return Str::studly($module);
    }

    public function exists(string $module): bool
    {
        return $this->files->isDirectory($this->path($module));
    }

    /**
     * Absolute path to the module root, e.g. /project/Modules/Blog.
     */
    public function path(string $module): string
    {
        return $this->rootPath().DIRECTORY_SEPARATOR.$this->name($module);
    }

    /**
     * Folder inside the module that holds PHP classes ("" or "app").
     */
    public function appFolder(string $module): string
    {
        $folder = $this->config('app_folder', 'auto');

        if ($folder === 'auto' || $folder === null) {
            return $this->files->isDirectory($this->path($module).DIRECTORY_SEPARATOR.'app') ? 'app' : '';
        }

        return trim((string) $folder, '/\\');
    }

    /**
     * Absolute path that maps onto the module namespace.
     */
    public function appPath(string $module): string
    {
        $folder = $this->appFolder($module);

        return $this->path($module).($folder !== '' ? DIRECTORY_SEPARATOR.$folder : '');
    }

    /**
     * Root namespace of the module, e.g. Modules\Blog.
     */
    public function namespace(string $module): string
    {
        return $this->rootNamespace().'\\'.$this->name($module);
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        if (! $this->files->isDirectory($this->rootPath())) {
            return [];
        }

        return collect($this->files->directories($this->rootPath()))
            ->map(fn (string $dir) => basename($dir))
            ->sort()
            ->values()
            ->all();
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->app['config']->get("repo-facade-builder.modules.{$key}", $default);
    }

    protected function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
