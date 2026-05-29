<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Scanner;

use CommerceXray\MagentoXray\StoreGraph\Entity;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\State;
use Throwable;

final class PlatformCollector implements CollectorInterface
{
    private ProductMetadataInterface $productMetadata;
    private State $appState;
    private DeploymentConfig $deploymentConfig;

    public function __construct(
        ProductMetadataInterface $productMetadata,
        State $appState,
        DeploymentConfig $deploymentConfig
    ) {
        $this->productMetadata = $productMetadata;
        $this->appState = $appState;
        $this->deploymentConfig = $deploymentConfig;
    }

    public function collect(ScanContext $context, ScanResult $result): void
    {
        $mode = null;
        try {
            $mode = $this->appState->getMode();
        } catch (Throwable $throwable) {
            $result->addError([
                'collector' => self::class,
                'message' => 'Unable to read Magento application mode.',
            ], $throwable);
        }

        $installDate = null;
        try {
            $installDate = $this->deploymentConfig->get('install/date');
        } catch (Throwable) {
            $installDate = null;
        }

        $edition = null;
        try {
            $edition = $this->productMetadata->getEdition();
        } catch (Throwable) {
            $edition = null;
        }

        $attributes = [
            'magento_name' => $this->safeProductMetadata('getName'),
            'magento_version' => $this->safeProductMetadata('getVersion'),
            'magento_edition' => $edition,
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'runtime_mode' => $mode,
            'install_date' => $installDate,
            'scan_profile' => $context->getProfile(),
            'scan_started_at' => $context->getStartedAtIso(),
        ];

        $result->addEntity(new Entity('platform', 'magento', 'Magento', $attributes));

        $result->setSummaryValue('magento_version', $attributes['magento_version']);
        $result->setSummaryValue('magento_edition', $attributes['magento_edition']);
        $result->setSummaryValue('php_version', $attributes['php_version']);
        $result->setSummaryValue('runtime_mode', $attributes['runtime_mode']);
    }

    private function safeProductMetadata(string $method): mixed
    {
        try {
            if (!method_exists($this->productMetadata, $method)) {
                return null;
            }

            return $this->productMetadata->{$method}();
        } catch (Throwable) {
            return null;
        }
    }
}
