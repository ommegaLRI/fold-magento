<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Console;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DoctorCommand extends Command
{
    public const COMMAND_NAME = 'xray:doctor';

    private DirectoryList $directoryList;

    public function __construct(DirectoryList $directoryList)
    {
        parent::__construct();
        $this->directoryList = $directoryList;
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Check whether fold-magento can run safely in this Magento installation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>fold-magento Doctor</info>');
        $output->writeln('');

        $checks = [
            $this->checkPhpVersion(),
            $this->checkMagentoRoot(),
            $this->checkAppEtcReadable(),
            $this->checkVarWritable(),
            $this->checkComposerLockReadable(),
        ];

        $hasFailure = false;

        foreach ($checks as $check) {
            [$ok, $label, $detail] = $check;
            $status = $ok ? '<info>OK</info>' : '<error>FAIL</error>';
            $output->writeln(sprintf('[%s] %s', $status, $label));

            if ($detail !== '') {
                $output->writeln(sprintf('     %s', $detail));
            }

            if (!$ok) {
                $hasFailure = true;
            }
        }

        $output->writeln('');

        if ($hasFailure) {
            $output->writeln('<error>fold-magento is installed, but one or more checks failed.</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<info>fold-magento is installed and ready for the MVP scan pipeline.</info>');
        return Cli::RETURN_SUCCESS;
    }

    /**
     * @return array{bool,string,string}
     */
    private function checkPhpVersion(): array
    {
        $required = 80100;
        $ok = PHP_VERSION_ID >= $required;

        return [
            $ok,
            'PHP version is supported',
            sprintf('Detected PHP %s; required PHP 8.1 or newer.', PHP_VERSION),
        ];
    }

    /**
     * @return array{bool,string,string}
     */
    private function checkMagentoRoot(): array
    {
        $root = $this->directoryList->getRoot();
        $ok = is_dir($root) && is_readable($root);

        return [
            $ok,
            'Magento root is readable',
            $root,
        ];
    }

    /**
     * @return array{bool,string,string}
     */
    private function checkAppEtcReadable(): array
    {
        $path = $this->directoryList->getRoot() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'etc';
        $ok = is_dir($path) && is_readable($path);

        return [
            $ok,
            'app/etc is readable',
            $path,
        ];
    }

    /**
     * @return array{bool,string,string}
     */
    private function checkVarWritable(): array
    {
        $path = $this->directoryList->getPath(DirectoryList::VAR_DIR);
        $ok = is_dir($path) && is_writable($path);

        return [
            $ok,
            'var directory is writable',
            $path,
        ];
    }

    /**
     * @return array{bool,string,string}
     */
    private function checkComposerLockReadable(): array
    {
        $path = $this->directoryList->getRoot() . DIRECTORY_SEPARATOR . 'composer.lock';
        $ok = is_file($path) && is_readable($path);

        return [
            $ok,
            'composer.lock is readable',
            $ok ? $path : $path . ' was not found or is not readable. Composer inventory may be limited.',
        ];
    }
}
