<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;

class StubRenderer
{
    public function __construct(
        protected Filesystem $files,
        protected Config $config,
    ) {}

    /**
     * Resolve a stub, preferring a published (customised) copy.
     */
    public function path(string $stub): string
    {
        $custom = $this->config->get('repo-facade-builder.stubs_path');

        if ($custom && $this->files->exists($file = rtrim($custom, '/\\')."/{$stub}.stub")) {
            return $file;
        }

        return static::defaultPath()."/{$stub}.stub";
    }

    /**
     * @param  array<string, string>  $replacements
     */
    public function render(string $stub, array $replacements): string
    {
        $contents = $this->files->get($this->path($stub));

        foreach ($replacements as $key => $value) {
            $contents = str_replace(["{{ {$key} }}", "{{{$key}}}"], $value, $contents);
        }

        return $contents;
    }

    public static function defaultPath(): string
    {
        return dirname(__DIR__, 2).'/stubs';
    }
}
