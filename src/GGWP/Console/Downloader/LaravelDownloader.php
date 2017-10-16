<?php

namespace GGWP\Console\Downloader;

class LaravelDownloader extends Downloader
{
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
