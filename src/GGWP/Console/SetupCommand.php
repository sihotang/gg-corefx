<?php

namespace GGWP\Console;

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

class SetupCommand extends Command
{
    /**
     * VERSION
     * Which compatible with GGWP Core.
     */
    const LARAVEL_VERSION   = '';
    const WORDPRESS_VERSION = '';

    /**
     * Without components installation
     *
     * @var array
     */
    protected $skips = [
        'laravel'   => ['.gitignore', '.gitattributes', 'composer.json', 'composer.lock', 'phpunit.xml'],
        'wordpress' => [],
    ];

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('setup')
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

        $directory = ($input->getArgument('name')) ? getcwd() . '/' . $input->getArgument('name') : getcwd();

        $version = $this->getVersion($input);

        $temp_fname = $this->makeRandomName('laravel_');
        $temp_zname = $temp_fname . '.zip';

        $skips = array();

        if ($input->getOption('skip-content') || $input->getOption('skip')) {
            $skips = ($input->getOption('skip-content')) ? $this->skips['laravel'] : $skips;
            $skips = ($input->getOption('skip')) ? array_merge($skips, $input->getOption('skip')) : $skips;
        }

        if (!$input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        $output->writeln('<info>Setup application...</info>');

        $this->download($zipFile = $temp_zname, $version)
            ->extract($zipFile, $temp_fname)
            ->prepareWritableDirectories($temp_fname, $output)
            ->move($temp_fname, $directory, $skips)
            ->prepareWritableDirectories($directory, $output)
            ->cleanUp($zipFile);

        $command = $this->findWPCLI();
        $command = $command . ' core download';
        $command = $command . ' --path=' . getcwd() . '/system';
        $command = $command . ' --locale=' .(($input->getOption('locale')) ? $input->getOption('locale') : 'id_ID');
        $command = $command . (($input->getOption('skip-content')) && ($input->getOption('locale') === 'en_US') ? ' --skip-content' : '');
        $command = $command . (($input->getOption('force')) ? ' --force' : '');

        $composer = $this->findComposer();

        $commands = [
            $command,
            $composer . ' update --no-scripts',
            $composer . ' run-script post-root-package-update',
            $composer . ' run-script post-autoload-dump',
        ];

        if ($input->getOption('no-ansi')) {
            $commands = array_map(function ($value) {
                return $value . ' --no-ansi';
            }, $commands);
        }

        $process = new Process(implode(' && ', $commands), $directory, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

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
    protected function download($zipFile, $version = 'master')
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
     *
     * @return string
     */
    protected function getVersion(InputInterface $input)
    {
        if ($input->getOption('dev')) {
            return 'develop';
        }

        return 'master';
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
