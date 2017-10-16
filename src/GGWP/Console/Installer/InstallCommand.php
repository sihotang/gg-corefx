<?php

namespace GGWP\Console\Installer;

use GGWP\Console\Downloader\LaravelDownloader;
use GGWP\Console\Downloader\WordPressDownloader;
use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use ZipArchive;

class InstallCommand extends Command
{
    const LARAVEL_VERSION   = '5.5.0';
    const WORDPRESS_VERSION = '4.8.2';

    private $config = [
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

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('install')
            ->setDescription('Create a new GGWP application.')
            ->addArgument('name', InputArgument::OPTIONAL)
            ->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Select which language want to download.')
            ->addOption('skip', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Install without component by input')
            ->addOption('skip-content', null, InputOption::VALUE_NONE, 'Install without component by using standard of GGWP, such as: composer.json, etc')
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forces install even if the directory already exists');
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface      $input
     * @param  \Symfony\Component\Console\Output\OutputInterface    $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
        }

        $downloader_LV = (new LaravelDownloader($this->getApplication(), $input, $output));
        $downloader_WP = (new WordPressDownloader($this->getApplication(), $input, $output, null, $downloader_LV->getTarget() . DIRECTORY_SEPARATOR . 'system'));

        $statusCode = $downloader_LV->run();

        if ($statusCode == 0) {
            $statusCode = $downloader_WP->run();
        }

        if (file_exists($downloader_LV->getTarget() . DIRECTORY_SEPARATOR . 'composer.json')) {
            $composer = $this->findComposer();

            $commands = [
                $composer . ' update --no-scripts',
                $composer . ' run-script post-root-package-update',
                $composer . ' run-script post-autoload-dump',
            ];

            if ($input->getOption('no-ansi')) {
                $commands = array_map(function ($value) {
                    return $value . ' --no-ansi';
                }, $commands);
            }

            $process = new Process(implode(' && ', $commands), $downloader_LV->getTarget(), null, null, null);

            if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
                $process->setTty(true);
            }

            $process->run(function ($type, $line) use ($output) {
                $output->write($line);
            });
        }

        $output->writeln('<comment>Application ready! Build something amazing.</comment>');
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     *
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory)
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Generate a random temporary folder name / filename.
     *
     * @param  string $prefix [description]
     *
     * @return string
     */
    protected function makeRandomName($prefix = 'ggwp_')
    {
        return getcwd() . '/' . $prefix . md5(time() . uniqid());
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string  $zipFile
     * @param  string  $version
     *
     * @return $this
     */
    protected function download($zipFile, $source, $version = 'master')
    {
        switch ($version) {
            case 'develop':
                $filename = 'latest-develop.zip';
                break;
            case 'master':
                $filename = 'latest.zip';
                break;
        }

        $response = (new Client)->get('http://cabinet.laravel.com/' . $filename);

        file_put_contents($zipFile, $response->getBody());

        return $this;
    }

    /**
     * Extract the Zip file into the given directory.
     *
     * @param  string  $zipFile
     * @param  string  $directory
     * @return $this
     */
    protected function extract($zipFile, $directory)
    {
        $archive = new ZipArchive;

        $archive->open($zipFile);

        $archive->extractTo($directory);

        $archive->close();

        return $this;
    }

    /**
     * Move all files on directory into given directory.
     *
     * @param  string $source
     * @param  string $destination
     * @param  array  $without
     *
     * @return $this
     */
    protected function move($source, $destination, $without = array())
    {
        $directory = opendir($source);

        @mkdir($destination);

        while (false !== ($file = readdir($directory))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($source . '/' . $file)) {
                    $this->move($source . '/' . $file, $destination . '/' . $file);
                } else {
                    if (!in_array($file, $without)) {
                        copy($source . '/' . $file, $destination . '/' . $file);
                    }

                    $this->cleanUp($source . '/' . $file);
                }
            }
        }

        closedir($directory);

        rmdir($source);

        return $this;
    }

    /**
     * Clean-up the file.
     *
     * @param  string  $filepath
     *
     * @return $this
     */
    protected function cleanUp($filepath)
    {
        @chmod($filepath, 0777);

        @unlink($filepath);

        return $this;
    }

    /**
     * Make sure the storage and bootstrap cache directories are writable.
     *
     * @param  string  $appDirectory
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     *
     * @return $this
     */
    protected function prepareWritableDirectories($appDirectory, OutputInterface $output)
    {
        $filesystem = new Filesystem;

        try {
            $filesystem->chmod($appDirectory . DIRECTORY_SEPARATOR . "bootstrap/cache", 0755, 0000, true);
            $filesystem->chmod($appDirectory . DIRECTORY_SEPARATOR . "storage", 0755, 0000, true);
        } catch (IOExceptionInterface $e) {
            $output->writeln('<comment>You should verify that the "storage" and "bootstrap/cache" directories are writable.</comment>');
        }

        return $this;
    }

    /**
     * Get the version that should be downloaded.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param string    $name
     * @param boolean   $isPrefixVersion    Default value false.
     *
     * @return string
     */
    protected function getVersion(InputInterface $input, $name, $isPrefixVersion = false)
    {
        if ($input->getOption('dev')) {
            return $this->config[$name]['version']['development'];
        }

        return $this->config[$name]['version']['default'] | $this->config[$name]['version']['latest'];
    }

    /**
     * Get the WP-CLI command for the environment.
     *
     * @return string
     */
    protected function findWPCLI()
    {
        if (file_exists(getcwd() . '/wp-cli.phar')) {
            return '"' . PHP_BINARY . '" wp-cli.phar';
        }

        if (file_exists('/usr/local/bin/wp')) {
            return 'wp';
        }

        return getcwd() . '/vendor/bin/wp';
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd() . '/composer.phar')) {
            return '"' . PHP_BINARY . '" composer.phar';
        }

        return 'composer';
    }
}
