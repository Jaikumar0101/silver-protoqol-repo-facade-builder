<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Commands\Concerns;

/**
 * Typed accessors for console input, which Symfony types as mixed.
 */
trait ReadsStringInput
{
    protected function stringArgument(string $key): string
    {
        $value = $this->argument($key);

        return is_string($value) ? $value : '';
    }

    /**
     * Returns null when the option was not given or is empty.
     */
    protected function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
