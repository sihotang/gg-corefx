<?php

namespace GG\Console\Downloader;

/**
 * Class Downloader for Laravel.
 *
 * @author Sopar Sihotang <soparsihotang@gmail.com>
 */
class LaravelDownloader extends Downloader
{
    /**
     * Configuration of Laravel Downloader.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('laravel')
            ->setVersion('latest')
            ->setDevelopmentVersion('develop')
            ->setURL('http://cabinet.laravel.com')
            ->setWritableDirs(array('bootstrap/cache', 'storage'))
            ->setSkips(array('.gitignore', '.gitattributes', 'composer.json', 'composer.lock', 'phpunit.xml'));
    }
}
