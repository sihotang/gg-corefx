<?php

namespace GGWP\Console\Downloader;

class WordPressDownloader extends Downloader
{
    protected function configure()
    {
        $this->setName('wordpress')
            ->setVersion('4.8.2')
            ->setDevelopmentVersion('nightly')
            ->setLocale((($this->getInput()->getOption('locale')) ? $this->getInput()->getOption('locale') : 'id_ID'))
            ->setCommand('wp core download', array(
                '--path'         => $this->getTarget(),
                '--locale'       => $this->getLocale(),
                '--version'      => $this->getVersion(),
                '--skip-content' => (($this->getInput()->getOption('skip-content')) && ($this->getInput()->getLocale() === 'en_US') ? true : false),
                '--force'        => $this->getForce(),
            ), 'wp-cli.phar')
            ->setSkips(array('.gitignore', '.gitattributes', 'composer.json', 'composer.lock', 'phpunit.xml'));
    }
}
