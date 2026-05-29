<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Doctor;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Throwable;

/**
 * Runs read-only environment checks for fold-magento.
 *
 * The class intentionally returns plain arrays so the console command can render
 * the checks without depending on another DTO layer in the MVP.
 */
final class DoctorCheckRunner
{
    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_FAIL = 'fail';

    private const MINIMUM_PHP_VERSION_ID = 80100;

    private DirectoryList $directoryList;
    private ?ResourceConnection $resourceConnection;

    public function __construct(DirectoryList $directoryList, ?ResourceConnection $resourceConnection = null)
    {
        $this->directoryList = $directoryList;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function run(): array
    {
        return [
            $this->checkPhpVersion(),
            $this->checkMagentoRoot(),
            $this->checkAppEtcReadable(),
            $this->checkVarWritable(),
            $this->checkXrayOutputWritable(),
            $this->checkComposerLockReadable(),
            $this->checkDatabaseReadable(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    public function hasFailures(array $checks): bool
    {
        foreach ($checks as $check) {
            if (($check['status'] ?? null) === self::STATUS_FAIL) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkPhpVersion(): array
    {
        $ok = PHP_VERSION_ID >= self::MINIMUM_PHP_VERSION_ID;

        return $this->buildCheck(
            'php_version',
            'PHP version is supported',
            $ok ? self::STATUS_OK : self::STATUS_FAIL,
            sprintf('Detected PHP %s; fold-magento requires PHP 8.1 or newer.', PHP_VERSION),
            $ok ? null : 'Run fold-magento with a PHP 8.1+ CLI binary.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkMagentoRoot(): array
    {
        $root = $this->directoryList->getRoot();
        $ok = is_dir($root) && is_readable($root);

        return $this->buildCheck(
            'magento_root_readable',
            'Magento root is readable',
            $ok ? self::STATUS_OK : self::STATUS_FAIL,
            $root,
            $ok ? null : 'Run the command from a readable Magento installation.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkAppEtcReadable(): array
    {
        $path = $this->pathFromRoot('app/etc');
        $ok = is_dir($path) && is_readable($path);

        return $this->buildCheck(
            'app_etc_readable',
            'app/etc is readable',
            $ok ? self::STATUS_OK : self::STATUS_FAIL,
            $path,
            $ok ? null : 'fold-magento needs read access to app/etc for local environment signals.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkVarWritable(): array
    {
        $path = $this->directoryList->getPath(DirectoryList::VAR_DIR);
        $ok = is_dir($path) && is_writable($path);

        return $this->buildCheck(
            'var_writable',
            'var directory is writable',
            $ok ? self::STATUS_OK : self::STATUS_FAIL,
            $path,
            $ok ? null : 'fold-magento writes local scan bundles under var/xray by default.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkXrayOutputWritable(): array
    {
        $path = $this->directoryList->getPath(DirectoryList::VAR_DIR) . DIRECTORY_SEPARATOR . 'xray';

        if (is_dir($path)) {
            $ok = is_writable($path);
        } else {
            $parent = dirname($path);
            $ok = is_dir($parent) && is_writable($parent);
        }

        return $this->buildCheck(
            'xray_output_writable',
            'fold-magento output location is writable',
            $ok ? self::STATUS_OK : self::STATUS_FAIL,
            $path,
            $ok ? null : 'Create var/xray or make the parent var directory writable by the CLI user.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkComposerLockReadable(): array
    {
        $path = $this->pathFromRoot('composer.lock');
        $ok = is_file($path) && is_readable($path);

        return $this->buildCheck(
            'composer_lock_readable',
            'composer.lock is readable',
            $ok ? self::STATUS_OK : self::STATUS_WARNING,
            $ok ? $path : $path . ' was not found or is not readable. Composer inventory may be limited.',
            $ok ? null : 'Provide read access to composer.lock for better package inventory.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDatabaseReadable(): array
    {
        if ($this->resourceConnection === null) {
            return $this->buildCheck(
                'database_readable',
                'Database connection is available',
                self::STATUS_WARNING,
                'ResourceConnection was not injected. Database-backed collectors may be unavailable.',
                'Check Magento dependency injection configuration if database collectors fail.'
            );
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $connection->fetchOne('SELECT 1');

            return $this->buildCheck(
                'database_readable',
                'Database connection is readable',
                self::STATUS_OK,
                'Read-only database probe succeeded.',
                null
            );
        } catch (Throwable $throwable) {
            return $this->buildCheck(
                'database_readable',
                'Database connection is readable',
                self::STATUS_FAIL,
                $throwable->getMessage(),
                'Confirm Magento can connect to the database from the CLI user.'
            );
        }
    }

    private function pathFromRoot(string $relativePath): string
    {
        return $this->directoryList->getRoot()
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($relativePath, '/\\'));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheck(
        string $code,
        string $label,
        string $status,
        string $detail = '',
        ?string $remediation = null
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'status' => $status,
            'ok' => $status === self::STATUS_OK,
            'detail' => $detail,
            'remediation' => $remediation,
        ];
    }
}
