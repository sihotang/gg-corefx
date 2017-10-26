<?php

namespace Syms\Console\Installer;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Syms\Console\Downloader\LaravelDownloader;
use Syms\Console\Downloader\WordPressDownloader;

/**
 * Class Command for Installation project application.
 *
 * @author Sopar Sihotang <soparsihotang@gmail.com>
 */
class InstallCommand extends Command
{
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

        $downloaderLV = (new LaravelDownloader($this->getApplication(), $input, $output));
        $downloaderWP = (new WordPressDownloader($this->getApplication(), $input, $output, null, $downloaderLV->getTarget() . DIRECTORY_SEPARATOR . 'system'));

        $statusCode = $downloaderLV->run();

        if ($statusCode == 0) {
            $statusCode = $downloaderWP->run();
        }

        if (file_exists($downloaderLV->getTarget() . DIRECTORY_SEPARATOR . 'composer.json')) {
            $composer = $this->findComposer();

            $commands = [
                $composer . ' update --no-scripts',
                $composer . ' run-script post-root-package-install',
                $composer . ' run-script post-autoload-dump',
            ];

            if ($input->getOption('no-ansi')) {
                $commands = array_map(function ($value) {
                    return $value . ' --no-ansi';
                }, $commands);
            }

            $process = new Process(implode(' && ', $commands), $downloaderLV->getTarget(), null, null, null);

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
