<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Theme Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your themes. Of course
    | the usual Wordpress theme path has already been registered for you.
    |
    */
    'paths' => [
        realpath(base_path('resources/themes')),
    ],
    /*
    |--------------------------------------------------------------------------
    | Compiled Theme Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    */
    'compiled' => realpath(storage_path('framework/themes')),
];