<?php

namespace GG\Console\Downloader;

use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Base class console for downloading application project.
 *
 * @author Sopar Sihotang <soparsihotang@gmail.com>
 */
abstract class Downloader
{
    /**
     * The Name of project.
     *
     * @var string
     */
    private $name;

    /**
     * The version of project.
     *
     * @var string
     */
    private $version;

    /**
     * The version development of project.
     * Default value: 'develop'
     *
     * @var string
     */
    private $versionDev = 'develop';

    /**
     * The url source for downloading project.
     *
     * @var string
     */
    private $url;

    /**
     * The command for downloading project,
     * Based on CLI on project.
     *
     * @var [type]
     */
    private $command;

    /**
     * The command parameters.
     *     ex: array ('--force' => false)
     *
     * @var array
     */
    private $commandArgs = array();

    /**
     * The name of phar file,
     * While executing command CLI of project.
     *     ex: 'wp-cli.phar'
     *
     * @var string
     */
    private $commandPhar = '';

    /**
     * The target directory for downloading.
     *
     * @var string
     */
    private $target;

    /**
     * The extension file based on resource.
     * Default value: zip
     *
     * @var string
     */
    private $extension = 'zip';

    /**
     * The language of source project.
     *     ex: en_US, id_ID, etc
     *
     * @var string
     */
    private $locale;

    /**
     * The version of project,
     * If true will be downloading dev ex: 0.1-dev
     * else will be downloading tag release.
     *
     * @var boolean
     */
    private $development = false;

    /**
     * If true will be replace existing directory on target,
     * else skip for replacing.
     *
     * @var boolean
     */
    private $force = false;

    /**
     * The contents for skipping download
     *     ex: '.gitignore, .gitattributes, composer.json, etc'.
     *
     * @var array
     */
    private $skips = array();

    /**
     * Change mod directories on project, while finished download.
     *
     * @var array
     */
    private $writableDirs = array();

    /**
     * Application based on command instance.
     *
     * @var Symfony\Component\Console\Application
     */
    private $application;

    /**
     * Input based on command instance.
     *
     * @var Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * Output based on command instance.
     *
     * @var Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * Constructor: create a new Downloader instance.
     *
     * @param Symfony\Component\Console\Application             $application
     * @param Symfony\Component\Console\Input\InputInterface    $input
     * @param Symfony\Component\Console\Output\OutputInterface  $output
     * @param string|null                                       $version
     * @param string|null                                       $target
     */
    public function __construct(Application $application, InputInterface $input, OutputInterface $output, $version = null, $target = null)
    {
        $this->application = $application;

        $this->input  = $input;
        $this->output = $output;

        if ($version !== null) {
            $this->setVersion($version);
        }

        if ($target !== null) {
            $this->setTarget($target);

        } else {
            $this->setTarget(($input->getArgument('name')) ? getcwd() . DIRECTORY_SEPARATOR . $input->getArgument('name') : getcwd());
        }

        if ($input->getOption('locale')) {
            $this->setLocale($input->getOption('locale'));
        }

        if ($input->getOption('dev')) {
            $this->setVersion($this->getDevelopmentVersion());
            $this->setDevelopment(true);
        }

        if ($input->getOption('force')) {
            $this->setForce(true);
        }

        $this->configure();

        if ($input->getOption('skip')) {
            array_merge($this->skips, $input->getOption('skip'));
        }
    }

    /**
     * Download action by using command,
     * represent CLI on project.
     *     ex: execution WP-CLI
     *
     * @return Downloader
     */
    protected function downloadByCommand()
    {
        $output = $this->output;

        $process = new Process($this->getCommandExecution(), $this->getTarget(), null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

        return $this;
    }

    /**
     * Download action by using URL resource.
     *
     * @return Downloader
     */
    protected function downloadByURL()
    {
        $input = $this->input;

        $tempName    = $this->makeRandomName();
        $tempDir     = getcwd() . DIRECTORY_SEPARATOR . $tempName;
        $archivePath = getcwd() . DIRECTORY_SEPARATOR . $tempName . '.' . $this->extension;

        $this->downloadArchive($archive = $archivePath)
            ->extract($archive, $tempDir)
            ->move($tempDir, $this->getTarget())
            ->rewriteDirs($this->writableDirs)
            ->cleanFile($archive);

        return $this;
    }

    /**
     * Download action for downloading archive.
     *
     * @param  string $archive
     *
     * @return Downloader
     */
    protected function downloadArchive($archive)
    {
        $response = (new Client)->get($this->url . '/' . $this->getVersion() . '.' . $this->extension);

        file_put_contents($archive, $response->getBody());

        return $this;
    }

    /**
     * Extract archive to directory.
     *
     * @param  string $file
     * @param  string $directory
     *
     * @return Downloader
     */
    protected function extract($file, $directory)
    {
        $archive = new ZipArchive;

        $archive->open($file);

        $archive->extractTo($directory);

        $archive->close();

        return $this;
    }

    /**
     * Moving all contents from directory to destination directory.
     *
     * @param  string $source
     * @param  string $destination
     *
     * @return Downloader
     */
    protected function move($source, $destination)
    {
        $directory = opendir($source);

        @mkdir($destination);

        while (false !== ($file = readdir($directory))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($source . '/' . $file)) {
                    $this->move($source . '/' . $file, $destination . '/' . $file);
                } else {
                    if (!in_array($file, $this->skips)) {
                        copy($source . '/' . $file, $destination . '/' . $file);
                    }

                    $this->cleanFile($source . '/' . $file);
                }
            }
        }

        closedir($directory);

        rmdir($source);

        return $this;
    }

    /**
     * Change mode | chmod directories to writable on array.
     *     action: chmod 755
     *
     * @param  array  $directories
     *
     * @return Downloader
     */
    protected function rewriteDirs($directories = array())
    {
        if (sizeof($directories) > 0) {
            $fs = new Filesystem;

            foreach ($directories as $directory) {
                try {
                    $fs->chmod($directory, 0755, 0000, true);
                } catch (IOExceptionInterface $e) {
                    $this->output->writeln('<comment>You should verify that the "' . $directory . '" are writable.</comment>');
                }
            }
        }

        return $this;
    }

    /**
     * Delete file.
     *
     * @param  string $path
     *
     * @return Downloader
     */
    protected function cleanFile($path)
    {
        @chmod($path, 0777);

        @unlink($path);

        return $this;
    }

    /**
     * Get random name based on time & uniqid.
     *
     * @return string
     */
    protected function makeRandomName()
    {
        return $this->name . md5(time() . uniqid());
    }

    /**
     * Finding command based on CLI of project,
     * Used while type download by command.
     *
     * @return string
     */
    protected function findCommand()
    {
        if (file_exists(getcwd() . DIRECTORY_SEPARATOR . $this->commandPhar)) {
            $command = '"' . PHP_BINARY . '" ' . $this->commandPhar;
        }

        $commandName = $this->getCommandName();

        if (file_exists('/usr/local/bin/' . $commandName)) {
            $command = $commandName;
        }

        $command = getcwd() . '/vendor/bin/' . $commandName;

        return $command;
    }

    /**
     * Verification application if already exists,
     * based on directory is exists.
     *
     * @return void
     *
     * @throws RuntimeException when application already exists.
     */
    protected function verify()
    {
        if ((is_dir($this->getTarget()) || is_file($this->getTarget())) && $this->getTarget() != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Global action download,
     * Check downloading if parameter command using force mode.
     *
     * @return Downloader
     */
    protected function download()
    {
        $output = $this->output;

        if (!$this->force) {
            $this->verify();
        }

        $output->writeln('<info>Initialize the ' . title_case($this->getName()) . ' project app.</info>');

        if ($this->command) {
            $this->downloadByCommand();
        } else {
            $this->downloadByURL();
        }

        $output->writeln('<info>' . title_case($this->getName()) . ' has been successfully initialized.</info>');

        return $this;
    }

    /**
     * Configure the current downloader.
     */
    protected function configure()
    {

    }

    /**
     * Runs the downloader.
     *
     * @return integer
     */
    public function run()
    {
        $statusCode = $this->download();

        return is_numeric($statusCode) ? (int) $statusCode : 0;
    }

    /**
     * Set the name of downloader.
     *
     * @param string $name
     *
     * @return Downloader
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Returns the downloader name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the version of downloader.
     *
     * @param string $version
     *
     * @return Downloader
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Returns the downloader version.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set the version development of downloader.
     *
     * @param string $version
     *
     * @return Downloader
     */
    public function setDevelopmentVersion($version)
    {
        $this->versionDev = $version;

        return $this;
    }

    /**
     * Returns the downloader version development.
     *
     * @return string
     */
    public function getDevelopmentVersion()
    {
        return $this->versionDev;
    }

    /**
     * Set the url of downloader.
     *
     * @param string $url
     *
     * @return Downloader
     */
    public function setURL($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Returns the downloader url.
     *
     * @return string
     */
    public function getURL()
    {
        return $this->url;
    }

    /**
     * Set the basic command, parameters of command,
     * and phar name of command downloader.
     *
     * @param string        $command
     * @param array         $args
     * @param string|empty  $phar
     *
     * @return Downloader
     */
    public function setCommand($command, $args = array(), $phar = '')
    {
        $this->command = $command;

        $this->commandArgs = $args;
        $this->commandPhar = $phar;

        return $this;
    }

    /**
     * Returns the downloader command.
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Returns the downloader command name.
     *     ex: wp core download,
     * Will returns 'wp' the first index.
     *
     * @return string
     */
    public function getCommandName()
    {
        return $this->getCommands()[0];
    }

    /**
     * Returns the downloader command execution.
     * All parameters included on execution.
     *
     * @return string
     */
    public function getCommandExecution()
    {
        $commands = $this->getCommands();

        unset($commands[0]);

        $args = implode(' ', array_map(
            function ($v, $k) {
                if (is_array($v)) {
                    return $k . '[]=' . implode('&' . $k . '[]=', $v);
                } elseif (is_bool($v)) {
                    return (!$v ? '' : $k);
                } else {
                    return $k . '=' . $v;
                }
            },
            $this->getCommandArgs(),
            array_keys($this->getCommandArgs())
        ));

        return $this->findCommand() . ' ' . implode(' ', $commands) . ' ' . $args;
    }

    /**
     * Returns the downloader parameters command.
     *
     * @return array
     */
    public function getCommandArgs()
    {
        return $this->commandArgs;
    }

    /**
     * Returns the downloader command phar name.
     *
     * @return string
     */
    public function getCommandPhar()
    {
        return $this->commandPhar;
    }

    /**
     * Returns the downloader commands.
     *     ex: wp core download
     * Will returns to array by using explode ' '.
     *
     * @return string
     */
    public function getCommands()
    {
        return explode(' ', $this->command);
    }

    /**
     * Set the target directory of downloader.
     *
     * @param string $target
     *
     * @return Downloader
     */
    public function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Returns the downloader path directory.
     *
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Set the name extension of downloader.
     *     ex: zip | tar.gz
     *
     * @param string $extension
     *
     * @return Downloader
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;

        return $this;
    }

    /**
     * Returns the downloader extension.
     *
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * Set the locale of downloader.
     *     ex: en_US, id_ID, etc.
     *
     * @param string $locale
     *
     * @return Downloader
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Returns the downloader locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set the resource of downloader.
     * If set to true, will be downloading resource development,
     * Will be download release version | latest.
     *
     * @param boolean $development
     *
     * @return Downloader
     */
    public function setDevelopment($development)
    {
        $this->development = $development;

        return $this;
    }

    /**
     * Returns the downloader resource.
     *
     * @return boolean
     */
    public function getDevelopment()
    {
        return $this->development;
    }

    /**
     * Set the force of downloader.
     * If set to true will be replacing existing directory.
     *
     * @param string $name
     *
     * @return Downloader
     */
    public function setForce($force)
    {
        $this->force = $force;

        return $this;
    }

    /**
     * Return the mode of downloader.
     *
     * @return boolean
     */
    public function getForce()
    {
        return $this->force;
    }

    /**
     * Set all skips content not needed on project.
     *     ex: .gitignore, .gitattributes, composer.json, etc.
     *
     * @param array $skips
     *
     * @return Downloader
     */
    public function setSkips($skips)
    {
        $this->skips = $skips;

        return $this;
    }

    /**
     * Returns the downloader skips content.
     *
     * @return array
     */
    public function getSkips()
    {
        return $this->skips;
    }

    /**
     * Set the writable directories of downloader.
     *
     * @param array $directories
     *
     * @return Downloader
     */
    public function setWritableDirs($directories)
    {
        array_walk($directories, function (&$value, $key) {
            $value = $this->getTarget() . DIRECTORY_SEPARATOR . $value;
        });

        $this->writableDirs = $directories;

        return $this;
    }

    /**
     * Returns the downloader directories writable.
     *
     * @return array
     */
    public function getWritableDirs()
    {
        return $this->writableDirs;
    }

    /**
     * Gets the input instance.
     *
     * @return InputInterface An InputInterface instance
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Gets the output instance.
     *
     * @return OutputInterface An OutputInterface instance
     */
    public function getOutput()
    {
        return $this->output;
    }
}
