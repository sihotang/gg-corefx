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
use ZipArchive;

class SetupCommand extends Command
{
    /**
     * Without components installation
     *
     * @var array
     */
    protected $without = [
        'laravel'   => [
            '.gitignore',
            '.gitattributes',
            'composer.json',
            'composer.lock',
            'phpunit.xml',
        ],
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
            ->addOption('without', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY)
            ->addOption('withouts', null, InputOption::VALUE_NONE, 'Install with standard without of GGWP')
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

        $temp_fname = $this->makeName();
        $temp_zname = $temp_fname . '.zip';

        $without = array();

        if ($input->getOption('withouts') || $input->getOption('without')) {
            $without = ($input->getOption('withouts')) ? $this->without['laravel'] : $without;
            $without = ($input->getOption('without')) ? array_merge($without, $input->getOption('without')) : $without;
        }

        if (!$input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        $output->writeln('<info>Setup application...</info>');

        $this->download($zipFile = $temp_zname, $version)
            ->extract($zipFile, $temp_fname)
            ->prepareWritableDirectories($temp_fname, $output)
            ->move($temp_fname, $directory, $without)
            ->prepareWritableDirectories($directory, $output)
            ->cleanUp($zipFile);

        // $composer = $this->findComposer();

        // $commands = [
        //     $composer . ' install --no-scripts',
        //     $composer . ' run-script post-root-package-install',
        //     $composer . ' run-script post-create-project-cmd',
        //     $composer . ' run-script post-autoload-dump',
        // ];

        // if ($input->getOption('dev')) {
        //     unset($commands[2]);

        //     $commands[] = $composer . ' run-script post-autoload-dump';
        // }

        // if ($input->getOption('no-ansi')) {
        //     $commands = array_map(function ($value) {
        //         return $value . ' --no-ansi';
        //     }, $commands);
        // }

        // $process = new Process(implode(' && ', $commands), $directory, null, null, null);

        // if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
        //     $process->setTty(true);
        // }

        // $process->run(function ($type, $line) use ($output) {
        //     $output->write($line);
        // });

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
     * @return string
     */
    protected function makeName()
    {
        return getcwd() . '/laravel_' . md5(time() . uniqid());
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd() . '/laravel_' . md5(time() . uniqid()) . '.zip';
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
     * [move description]
     *
     * @param  [type] $source      [description]
     * @param  [type] $destination [description]
     * @param  array  $without     [description]
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
     * Clean-up the Zip file.
     *
     * @param  string  $zipFile
     *
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);

        @unlink($zipFile);

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
