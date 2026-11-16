<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Commands;

class MakeServiceCommand extends GeneratorCommand
{
    protected $signature = 'make:service
        {name : Service name, sub-folders allowed (e.g. Payment/Stripe)}
        {--r|repository= : Inject a repository interface (name like "User" or a fully-qualified interface)}';

    protected $description = 'Create a new service class';

    public function handle(): int
    {
        if (! $this->resolveContext()) {
            return self::FAILURE;
        }

        [$segments, $name] = $this->parseName($this->stringArgument('name'), 'Service');
        [$directory, $namespace] = $this->location('service', $segments);

        $class = "{$name}Service";
        $repository = $this->resolveRepository();

        $this->writeStub(
            "{$directory}/{$class}.php",
            $repository ? 'service.repository' : 'service',
            [
                'namespace' => $namespace,
                'class' => $class,
                'repository' => $repository ? class_basename($repository) : '',
                'imports' => $this->imports($namespace, [$repository]),
            ]
        );

        return self::SUCCESS;
    }

    protected function resolveRepository(): ?string
    {
        $repository = $this->stringOption('repository');

        if (! $repository) {
            return null;
        }

        $repository = str_replace('/', '\\', trim($repository, '/\\'));

        // Already fully-qualified within the current root namespace (or anywhere that autoloads).
        if (str_starts_with($repository, $this->baseNamespace().'\\') || interface_exists($repository)) {
            return $repository;
        }

        [$segments, $name] = $this->parseName($repository, 'RepositoryInterface');
        [$segments, $name] = $this->parseName(implode('/', [...$segments, $name]), 'Repository');

        [, $namespace] = $this->location(
            'repository',
            $segments,
            config('repo-facade-builder.repository.interface_folder') ?: null
        );

        return "{$namespace}\\{$name}RepositoryInterface";
    }
}
