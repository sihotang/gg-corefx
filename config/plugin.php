<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Plugin Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your plugins. Of course
    | the usual Wordpress plugin path has already been registered for you.
    |
    */
    'paths' => [
        realpath(base_path('resources/plugins')),
    ],
    /*
    |--------------------------------------------------------------------------
    | Compiled Plugin Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    */
    'compiled' => realpath(storage_path('framework/plugins')),
];