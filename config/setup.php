<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Configuration Installation for Laravel
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your themes. Of course
    | the usual Wordpress theme path has already been registered for you.
    |
     */
    'laravel'   => [
        'version' => [
            'release'     => '5.5.0',
            'development' => 'develop',
            'latest'      => 'latest',
            'default'     => '5.5.0',
        ],
        'source'  => [
            'https://github.com/laravel/laravel/releases/',
            'http://cabinet.laravel.com/',
        ],

        /**
         * Skip files while installation for initialize project
         * While creating project this files will be skipping by system.
         */
        'skips'   => [
            // Git Components
            '.gitignore',
            '.gitattributes',

            // Composer Components
            'composer.json',
            'composer.lock',

            // PHP-Unit Components
            'phpunit.xml',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Installation for WordPress
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your themes. Of course
    | the usual Wordpress theme path has already been registered for you.
    |
     */
    'wordpress' => [
        'version' => [
            'release'     => '4.8.2',
            'development' => 'nightly',
            'latest'      => 'latest',
            'default'     => 'latest',
        ],

        /**
         * Skip files while installation for initialize project
         * While creating project this files will be skipping by system.
         */
        'skips'   => [
            // Git Components
            '.gitignore',
            '.gitattributes',

            // Composer Components
            'composer.json',
            'composer.lock',

            // PHP-Unit Components
            'phpunit.xml',
        ],
    ],

];
