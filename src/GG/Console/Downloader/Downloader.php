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

abstract class Downloader
{
    private $name;

    private $version;

    private $version_dev = 'develop';

    private $url;

    private $command;

    private $command_args = array();

    private $command_phar = '';

    private $target;

    private $extension = 'zip';

    private $locale;

    private $development = false;

    private $force = false;

    private $skips = array();

    private $writeable_dirs = array();

    private $application;

    private $input;

    private $output;

    public function __construct(Application $application, InputInterface $input, OutputInterface $output, $version = null, $target = null)
    {
        $this->application = $application;

        $this->input = $input;

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

    protected function downloadByURL()
    {
        $input = $this->input;

        $temp_name    = $this->makeRandomName();
        $temp_dir     = getcwd() . DIRECTORY_SEPARATOR . $temp_name;
        $archive_path = getcwd() . DIRECTORY_SEPARATOR . $temp_name . '.' . $this->extension;

        $this->downloadArchive($archive = $archive_path)
            ->extract($archive, $temp_dir)
            ->move($temp_dir, $this->getTarget())
            ->rewriteDirs($this->writable_dirs)
            ->cleanFile($archive);

        return $this;
    }

    protected function downloadArchive($archive)
    {
        $response = (new Client)->get($this->url . '/' . $this->getVersion() . '.' . $this->extension);

        file_put_contents($archive, $response->getBody());

        return $this;
    }

    protected function extract($file, $directory)
    {
        $archive = new ZipArchive;

        $archive->open($file);

        $archive->extractTo($directory);

        $archive->close();

        return $this;
    }

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

    protected function cleanFile($path)
    {
        @chmod($path, 0777);

        @unlink($path);

        return $this;
    }

    protected function makeRandomName()
    {
        return $this->name . md5(time() . uniqid());
    }

    protected function findCommand()
    {
        if (file_exists(getcwd() . DIRECTORY_SEPARATOR . $this->command_phar)) {
            $command = '"' . PHP_BINARY . '" ' . $this->command_phar;
        }

        $command_name = $this->getCommandName();

        if (file_exists('/usr/local/bin/' . $command_name)) {
            $command = $command_name;
        }

        $command = getcwd() . '/vendor/bin/' . $command_name;

        return $command;
    }

    protected function verify()
    {
        if ((is_dir($this->getTarget()) || is_file($this->getTarget())) && $this->getTarget() != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    protected function download()
    {
        $output = $this->output;

        if (!$this->force) {
            $this->verify();
        }

        $output->writeln('<info>Initialize the '. title_case($this->getName()) . ' project app.</info>');

        if ($this->command) {
            $this->downloadByCommand();
        } else {
            $this->downloadByURL();
        }

        $output->writeln('<info>'. title_case($this->getName()) . ' has been successfully initialized.</info>');

        return $this;
    }

    protected function configure()
    {

    }

    public function run()
    {
        $statusCode = $this->download();

        return is_numeric($statusCode) ? (int) $statusCode : 0;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function setDevelopmentVersion($version)
    {
        $this->version_dev = $version;

        return $this;
    }

    public function getDevelopmentVersion()
    {
        return $this->version_dev;
    }

    public function setURL($url)
    {
        $this->url = $url;

        return $this;
    }

    public function getURL()
    {
        return $this->url;
    }

    public function setCommand($command, $args = array(), $phar = '')
    {
        $this->command = $command;

        $this->command_args = $args;
        $this->command_phar = $phar;

        return $this;
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getCommandName()
    {
        return $this->getCommands()[0];
    }

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

    public function getCommandArgs()
    {
        return $this->command_args;
    }

    public function getCommandPhar()
    {
        return $this->command_phar;
    }

    public function getCommands()
    {
        return explode(' ', $this->command);
    }

    public function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }

    public function getTarget()
    {
        return $this->target;
    }

    public function setExtension($extension)
    {
        $this->extension = $extension;

        return $this;
    }

    public function getExtension()
    {
        return $this->extension;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setDevelopment($development)
    {
        $this->development = $development;

        return $this;
    }

    public function getDevelopment()
    {
        return $this->development;
    }

    public function setForce($force)
    {
        $this->force = $force;

        return $this;
    }

    public function getForce()
    {
        return $this->force;
    }

    public function setSkips($skips)
    {
        $this->skips = $skips;

        return $this;
    }

    public function getSkips()
    {
        return $this->skips;
    }

    public function setWritableDirs($directories)
    {
        array_walk($directories, function (&$value, $key) {
            $value = $this->getTarget() . DIRECTORY_SEPARATOR . $value;
        });

        $this->writable_dirs = $directories;

        return $this;
    }

    public function getWritableDirs()
    {
        return $this->writable_dirs;
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
