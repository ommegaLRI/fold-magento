<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Scanner;

use CommerceXray\MagentoXray\StoreGraph\Entity;
use CommerceXray\MagentoXray\StoreGraph\Relationship;
use Magento\Framework\App\Filesystem\DirectoryList;
use Throwable;

final class ComposerCollector implements CollectorInterface
{
    private DirectoryList $directoryList;

    public function __construct(DirectoryList $directoryList)
    {
        $this->directoryList = $directoryList;
    }

    public function collect(ScanContext $context, ScanResult $result): void
    {
        unset($context);

        $root = $this->directoryList->getRoot();
        $packages = $this->readInstalledPackages($root);
        $source = 'vendor/composer/installed.php';

        if ($packages === []) {
            $packages = $this->readComposerLockPackages($root);
            $source = 'composer.lock';
        }

        if ($packages === []) {
            $result->addError([
                'collector' => self::class,
                'message' => 'No Composer package inventory found in vendor/composer/installed.php or composer.lock.',
            ]);
            $result->setSummaryValue('composer_package_count', 0);
            return;
        }

        usort($packages, static fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        $thirdPartyCount = 0;
        $magentoPackageCount = 0;

        foreach ($packages as $package) {
            $name = isset($package['name']) ? (string) $package['name'] : '';
            if ($name === '') {
                continue;
            }

            $classification = $this->classifyPackage($name);
            if ($classification === 'third_party') {
                $thirdPartyCount++;
            }
            if ($classification === 'magento_or_adobe') {
                $magentoPackageCount++;
            }

            $result->addEntity(new Entity(
                'composer_package',
                $name,
                $name,
                [
                    'name' => $name,
                    'version' => $package['version'] ?? $package['pretty_version'] ?? null,
                    'pretty_version' => $package['pretty_version'] ?? null,
                    'type' => $package['type'] ?? null,
                    'license' => $this->normalizeLicense($package['license'] ?? null),
                    'classification' => $classification,
                    'source' => $source,
                ]
            ));

            $result->addRelationship(new Relationship(
                'has_package',
                'platform',
                'magento',
                'composer_package',
                $name,
                ['classification' => $classification]
            ));
        }

        $result->setSummaryValue('composer_package_count', count($packages));
        $result->setSummaryValue('composer_magento_package_count', $magentoPackageCount);
        $result->setSummaryValue('composer_third_party_package_count', $thirdPartyCount);
    }

    /** @return array<int, array<string, mixed>> */
    private function readInstalledPackages(string $root): array
    {
        $path = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.php';
        if (!is_readable($path)) {
            return [];
        }

        try {
            $installed = include $path;
        } catch (Throwable) {
            return [];
        }

        if (!is_array($installed)) {
            return [];
        }

        $packages = [];

        if (isset($installed['versions']) && is_array($installed['versions'])) {
            foreach ($installed['versions'] as $name => $data) {
                $data = is_array($data) ? $data : [];
                $data['name'] = (string) $name;
                $packages[] = $data;
            }

            return $packages;
        }

        if (array_is_list($installed)) {
            foreach ($installed as $vendorSet) {
                if (!is_array($vendorSet)) {
                    continue;
                }

                foreach (($vendorSet['versions'] ?? []) as $name => $data) {
                    $data = is_array($data) ? $data : [];
                    $data['name'] = (string) $name;
                    $packages[] = $data;
                }
            }
        }

        return $packages;
    }

    /** @return array<int, array<string, mixed>> */
    private function readComposerLockPackages(string $root): array
    {
        $path = $root . DIRECTORY_SEPARATOR . 'composer.lock';
        if (!is_readable($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        $packages = [];
        foreach (['packages', 'packages-dev'] as $key) {
            foreach (($data[$key] ?? []) as $package) {
                if (is_array($package)) {
                    $package['source_section'] = $key;
                    $packages[] = $package;
                }
            }
        }

        return $packages;
    }

    private function classifyPackage(string $name): string
    {
        if (str_starts_with($name, 'magento/') || str_starts_with($name, 'adobe/')) {
            return 'magento_or_adobe';
        }

        if (str_starts_with($name, 'laminas/')
            || str_starts_with($name, 'symfony/')
            || str_starts_with($name, 'composer/')
            || str_starts_with($name, 'psr/')
        ) {
            return 'platform_dependency';
        }

        return 'third_party';
    }

    private function normalizeLicense(mixed $license): mixed
    {
        if (is_array($license)) {
            return array_values(array_map('strval', $license));
        }

        return $license === null ? null : (string) $license;
    }
}
