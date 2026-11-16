<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Commands;

class MakeInterfaceCommand extends GeneratorCommand
{
    protected $signature = 'make:interface
        {name : Interface name, sub-folders allowed (e.g. Payments/Gateway)}';

    protected $description = 'Create a new interface';

    public function handle(): int
    {
        if (! $this->resolveContext()) {
            return self::FAILURE;
        }

        [$segments, $name] = $this->parseName($this->argument('name'), 'Interface');
        [$directory, $namespace] = $this->location('interface', $segments);

        $this->writeStub("{$directory}/{$name}Interface.php", 'interface', [
            'namespace' => $namespace,
            'class' => "{$name}Interface",
        ]);

        return self::SUCCESS;
    }
}
