<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Scanner;

use CommerceXray\MagentoXray\StoreGraph\Entity;
use CommerceXray\MagentoXray\StoreGraph\Relationship;
use Magento\Framework\Module\FullModuleList;
use Magento\Framework\Module\Manager as ModuleManager;
use Throwable;

final class ModuleCollector implements CollectorInterface
{
    private FullModuleList $fullModuleList;
    private ModuleManager $moduleManager;

    public function __construct(FullModuleList $fullModuleList, ModuleManager $moduleManager)
    {
        $this->fullModuleList = $fullModuleList;
        $this->moduleManager = $moduleManager;
    }

    public function collect(ScanContext $context, ScanResult $result): void
    {
        unset($context);

        $modules = $this->fullModuleList->getAll();
        ksort($modules);

        $enabledCount = 0;
        $disabledCount = 0;

        foreach ($modules as $moduleName => $moduleConfig) {
            $moduleConfig = is_array($moduleConfig) ? $moduleConfig : [];
            $enabled = $this->isModuleEnabled((string) $moduleName);
            $enabled ? $enabledCount++ : $disabledCount++;

            $sequence = $this->extractSequence($moduleConfig);

            $result->addEntity(new Entity(
                'magento_module',
                (string) $moduleName,
                (string) $moduleName,
                [
                    'name' => (string) $moduleName,
                    'enabled' => $enabled,
                    'setup_version' => $moduleConfig['setup_version'] ?? null,
                    'sequence' => $sequence,
                    'vendor' => $this->moduleVendor((string) $moduleName),
                    'is_likely_core' => $this->isLikelyCoreModule((string) $moduleName),
                ]
            ));

            $result->addRelationship(new Relationship(
                'has_module',
                'platform',
                'magento',
                'magento_module',
                (string) $moduleName,
                ['enabled' => $enabled]
            ));

            foreach ($sequence as $dependencyName) {
                $result->addRelationship(new Relationship(
                    'depends_on',
                    'magento_module',
                    (string) $moduleName,
                    'magento_module',
                    (string) $dependencyName
                ));
            }
        }

        $result->setSummaryValue('module_count', count($modules));
        $result->setSummaryValue('enabled_module_count', $enabledCount);
        $result->setSummaryValue('disabled_module_count', $disabledCount);
    }

    private function isModuleEnabled(string $moduleName): bool
    {
        try {
            return $this->moduleManager->isEnabled($moduleName);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $moduleConfig
     * @return string[]
     */
    private function extractSequence(array $moduleConfig): array
    {
        $sequence = $moduleConfig['sequence'] ?? [];

        if (is_string($sequence)) {
            return [$sequence];
        }

        if (!is_array($sequence)) {
            return [];
        }

        $items = [];
        foreach ($sequence as $key => $value) {
            if (is_string($value)) {
                $items[] = $value;
                continue;
            }

            if (is_string($key)) {
                $items[] = $key;
            }
        }

        sort($items);

        return array_values(array_unique($items));
    }

    private function moduleVendor(string $moduleName): ?string
    {
        $parts = explode('_', $moduleName, 2);
        return $parts[0] !== '' ? $parts[0] : null;
    }

    private function isLikelyCoreModule(string $moduleName): bool
    {
        return str_starts_with($moduleName, 'Magento_')
            || str_starts_with($moduleName, 'Adobe_')
            || str_starts_with($moduleName, 'PayPal_');
    }
}
