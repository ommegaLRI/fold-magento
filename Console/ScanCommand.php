<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Console;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ScanCommand extends Command
{
    public const COMMAND_NAME = 'xray:scan';

    private const DEFAULT_PROFILE = 'minimal';
    private const DEFAULT_OUTPUT = 'var/xray';

    private DirectoryList $directoryList;

    public function __construct(DirectoryList $directoryList)
    {
        parent::__construct();
        $this->directoryList = $directoryList;
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Run a private read-only fold-magento discovery scan.');
        $this->addOption(
            'profile',
            null,
            InputOption::VALUE_REQUIRED,
            'Scan profile to run. The MVP supports minimal.',
            self::DEFAULT_PROFILE
        );
        $this->addOption(
            'output',
            null,
            InputOption::VALUE_REQUIRED,
            'Output directory for scan bundles, relative to the Magento root unless absolute.',
            self::DEFAULT_OUTPUT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = (string) $input->getOption('profile');
        $outputOption = (string) $input->getOption('output');

        if ($profile !== self::DEFAULT_PROFILE) {
            $output->writeln(sprintf('<error>Unsupported scan profile: %s</error>', $profile));
            $output->writeln('<comment>The Tier 1 MVP scaffold only recognizes --profile=minimal.</comment>');
            return Cli::RETURN_FAILURE;
        }

        $targetDirectory = $this->resolveOutputDirectory($outputOption);

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            $output->writeln(sprintf('<error>Could not create output directory: %s</error>', $targetDirectory));
            return Cli::RETURN_FAILURE;
        }

        if (!is_writable($targetDirectory)) {
            $output->writeln(sprintf('<error>Output directory is not writable: %s</error>', $targetDirectory));
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<info>fold-magento Scan</info>');
        $output->writeln('');
        $output->writeln(sprintf('Profile: <comment>%s</comment>', $profile));
        $output->writeln(sprintf('Output:  <comment>%s</comment>', $targetDirectory));
        $output->writeln('');
        $output->writeln('<comment>Tier 1 scaffold is installed and executable.</comment>');
        $output->writeln('<comment>The scan runner, collectors, and bundle writers are added in Tier 2 and Tier 3.</comment>');

        return Cli::RETURN_SUCCESS;
    }

    private function resolveOutputDirectory(string $output): string
    {
        if ($output === '') {
            $output = self::DEFAULT_OUTPUT;
        }

        if ($this->isAbsolutePath($output)) {
            return rtrim($output, DIRECTORY_SEPARATOR);
        }

        return $this->directoryList->getRoot()
            . DIRECTORY_SEPARATOR
            . trim($output, DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === DIRECTORY_SEPARATOR) {
            return true;
        }

        return (bool) preg_match('/^[A-Z]:\\\\/i', $path);
    }
}
