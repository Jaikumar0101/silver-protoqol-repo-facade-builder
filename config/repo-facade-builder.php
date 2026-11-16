<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Target folders
    |--------------------------------------------------------------------------
    |
    | Folder (relative to `app/`, or to a module's root) that each generator
    | writes into. The namespace is derived from the folder automatically.
    |
    */

    'paths' => [
        'repository' => 'Repositories',
        'service' => 'Services',
        'facade' => 'Facades',
        'enum' => 'Enums',
        'interface' => 'Interfaces',
        'trait' => 'Traits',
        'provider' => 'Providers',
        'model' => 'Models',
    ],

    /*
    |--------------------------------------------------------------------------
    | Repositories
    |--------------------------------------------------------------------------
    |
    | interface_folder: null keeps the interface next to the repository. Set to
    |                   e.g. "Contracts" to place it in Repositories/Contracts.
    | provider:         class name of the provider that holds interface =>
    |                   implementation bindings when using `--bind`.
    |
    */

    'repository' => [
        'interface_folder' => null,
        'provider' => 'RepositoryServiceProvider',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | path:       directory holding all modules (relative to base_path()).
    | namespace:  root namespace of all modules.
    | app_folder: sub-folder inside a module that holds the PHP classes.
    |             "auto" uses "app" when the module has one (nwidart/laravel-modules
    |             v11+ layout) and the module root otherwise. Set "" or "app" to force.
    | scaffold:   folders created by `make:module`.
    |
    */

    'modules' => [
        'path' => 'Modules',
        'namespace' => 'Modules',
        'app_folder' => 'auto',
        'scaffold' => [
            'Http/Controllers',
            'Models',
            'Repositories',
            'Services',
            'Providers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom stubs
    |--------------------------------------------------------------------------
    |
    | Run `php artisan vendor:publish --tag=repo-facade-builder-stubs` to copy
    | the stubs here and customise them. Missing stubs fall back to defaults.
    |
    */

    'stubs_path' => base_path('stubs/repo-facade-builder'),

];
