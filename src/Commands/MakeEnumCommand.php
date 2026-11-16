<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Commands;

class MakeEnumCommand extends GeneratorCommand
{
    protected $signature = 'make:enum
        {name : Enum name, sub-folders allowed (e.g. Orders/OrderStatus)}
        {--t|type= : Backing type: string or int}';

    protected $description = 'Create a new enum';

    public function handle(): int
    {
        if (! $this->resolveContext()) {
            return self::FAILURE;
        }

        $type = $this->stringOption('type');
        $type = $type !== null ? strtolower($type) : null;

        if ($type !== null && ! in_array($type, ['string', 'int'], true)) {
            $this->components->error('The --type option must be "string" or "int".');

            return self::FAILURE;
        }

        [$segments, $class] = $this->parseName($this->stringArgument('name'));
        [$directory, $namespace] = $this->location('enum', $segments);

        $this->writeStub("{$directory}/{$class}.php", $type ? "enum.{$type}" : 'enum', [
            'namespace' => $namespace,
            'class' => $class,
        ]);

        return self::SUCCESS;
    }
}
