<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Support;

use Illuminate\Filesystem\Filesystem;

/**
 * Maintains the `$bindings` array of a RepositoryServiceProvider.
 */
class BindingRegistrar
{
    public const MARKER = '// @repo-facade-builder:bindings';

    public const ADDED = 'added';

    public const EXISTS = 'exists';

    public const NO_MARKER = 'no-marker';

    public function __construct(protected Filesystem $files) {}

    public function add(string $providerPath, string $abstract, string $concrete): string
    {
        $contents = $this->files->get($providerPath);
        $abstract = '\\'.ltrim($abstract, '\\');
        $concrete = '\\'.ltrim($concrete, '\\');

        if (str_contains($contents, "{$abstract}::class =>")) {
            return self::EXISTS;
        }

        if (! preg_match('/^([ \t]*)'.preg_quote(self::MARKER, '/').'/m', $contents, $match, PREG_OFFSET_CAPTURE)) {
            return self::NO_MARKER;
        }

        // Insert a new line, indented like the marker, directly above it.
        $offset = $match[0][1];
        $indent = $match[1][0];

        $contents = substr($contents, 0, $offset)
            ."{$indent}{$abstract}::class => {$concrete}::class,\n"
            .substr($contents, $offset);

        $this->files->put($providerPath, $contents);

        return self::ADDED;
    }

    public static function line(string $abstract, string $concrete): string
    {
        return '\\'.ltrim($abstract, '\\').'::class => \\'.ltrim($concrete, '\\').'::class,';
    }
}
