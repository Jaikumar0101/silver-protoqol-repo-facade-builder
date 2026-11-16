<?php

namespace SilverProtoQoL\RepoFacadeBuilder\Commands;

use Illuminate\Support\Str;

class MakeFacadeCommand extends GeneratorCommand
{
    protected $signature = 'make:facade
        {name : Facade name, sub-folders allowed (e.g. Billing/Payment)}
        {--s|service : Also create the backing service and point the facade at it (no manual binding needed)}
        {--accessor= : Custom container key returned by getFacadeAccessor()}';

    protected $description = 'Create a new facade class';

    public function handle(): int
    {
        if (! $this->resolveContext()) {
            return self::FAILURE;
        }

        [$segments, $class] = $this->parseName($this->argument('name'), 'Facade');
        [$directory, $namespace] = $this->location('facade', $segments);

        $service = null;

        if ($this->option('service')) {
            [, $serviceName] = $this->parseName($class, 'Service');
            [, $serviceNamespace] = $this->location('service', $segments);
            $service = "{$serviceNamespace}\\{$serviceName}Service";

            $this->call('make:service', [
                'name' => implode('/', [...$segments, $serviceName]),
                ...$this->forwardedOptions(),
            ]);
        }

        $accessor = match (true) {
            (bool) $this->option('accessor') => var_export((string) $this->option('accessor'), true),
            $service !== null => '\\'.$service.'::class',
            default => var_export(Str::snake($class), true),
        };

        $this->writeStub("{$directory}/{$class}.php", 'facade', [
            'namespace' => $namespace,
            'class' => $class,
            'accessor' => $accessor,
            'see' => $service ? "\n *\n * @see \\{$service}" : '',
            'imports' => $this->imports($namespace, ['Illuminate\Support\Facades\Facade']),
        ]);

        if (! $service && ! $this->option('accessor')) {
            $this->components->bulletList([
                'Bind the accessor in a service provider:',
                "\$this->app->singleton('".Str::snake($class)."', fn () => new YourService());",
                'Or re-run with --service to generate and wire a service automatically.',
            ]);
        }

        return self::SUCCESS;
    }
}
