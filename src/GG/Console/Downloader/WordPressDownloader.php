<?php

namespace GG\Console\Downloader;

/**
 * Class Downloader for WordPress.
 *
 * @author Sopar Sihotang <soparsihotang@gmail.com>
 */
class WordPressDownloader extends Downloader
{
    /**
     * Configuration of WordPress Downloader.
     *
     * @return void
     */
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
