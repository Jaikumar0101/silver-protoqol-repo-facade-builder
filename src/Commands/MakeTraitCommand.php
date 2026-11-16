<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Commands;

class MakeTraitCommand extends GeneratorCommand
{
    protected $signature = 'make:trait
        {name : Trait name, sub-folders allowed (e.g. Concerns/HasUuid)}';

    protected $description = 'Create a new trait';

    public function handle(): int
    {
        if (! $this->resolveContext()) {
            return self::FAILURE;
        }

        [$segments, $class] = $this->parseName($this->stringArgument('name'));
        [$directory, $namespace] = $this->location('trait', $segments);

        $this->writeStub("{$directory}/{$class}.php", 'trait', [
            'namespace' => $namespace,
            'class' => $class,
        ]);

        return self::SUCCESS;
    }
}
