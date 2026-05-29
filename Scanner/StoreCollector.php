<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Scanner;

use CommerceXray\MagentoXray\StoreGraph\Entity;
use CommerceXray\MagentoXray\StoreGraph\Relationship;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

final class StoreCollector implements CollectorInterface
{
    private StoreManagerInterface $storeManager;

    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    public function collect(ScanContext $context, ScanResult $result): void
    {
        unset($context);

        $websiteCount = 0;
        $storeGroupCount = 0;
        $storeViewCount = 0;
        $activeStoreViewCount = 0;

        foreach ($this->safeWebsites($result) as $website) {
            $websiteId = (string) $website->getId();
            $websiteCode = (string) $website->getCode();
            $websiteCount++;

            $result->addEntity(new Entity(
                'website',
                $websiteId,
                $websiteCode,
                [
                    'id' => (int) $website->getId(),
                    'code' => $websiteCode,
                    'name' => (string) $website->getName(),
                    'sort_order' => $this->safeCall($website, 'getSortOrder'),
                    'default_group_id' => $this->safeCall($website, 'getDefaultGroupId'),
                ]
            ));

            $result->addRelationship(new Relationship(
                'has_website',
                'platform',
                'magento',
                'website',
                $websiteId
            ));
        }

        foreach ($this->safeGroups($result) as $group) {
            $groupId = (string) $group->getId();
            $websiteId = (string) $group->getWebsiteId();
            $storeGroupCount++;

            $result->addEntity(new Entity(
                'store_group',
                $groupId,
                (string) $group->getName(),
                [
                    'id' => (int) $group->getId(),
                    'name' => (string) $group->getName(),
                    'website_id' => (int) $group->getWebsiteId(),
                    'root_category_id' => $this->safeCall($group, 'getRootCategoryId'),
                    'default_store_id' => $this->safeCall($group, 'getDefaultStoreId'),
                ]
            ));

            $result->addRelationship(new Relationship(
                'has_store_group',
                'website',
                $websiteId,
                'store_group',
                $groupId
            ));
        }

        foreach ($this->safeStores($result) as $store) {
            $storeId = (string) $store->getId();
            $groupId = (string) $store->getStoreGroupId();
            $isActive = (bool) $store->getIsActive();
            $storeViewCount++;
            if ($isActive) {
                $activeStoreViewCount++;
            }

            $result->addEntity(new Entity(
                'store_view',
                $storeId,
                (string) $store->getCode(),
                [
                    'id' => (int) $store->getId(),
                    'code' => (string) $store->getCode(),
                    'name' => (string) $store->getName(),
                    'website_id' => (int) $store->getWebsiteId(),
                    'group_id' => (int) $store->getStoreGroupId(),
                    'is_active' => $isActive,
                    'sort_order' => $this->safeCall($store, 'getSortOrder'),
                ]
            ));

            $result->addRelationship(new Relationship(
                'has_store_view',
                'store_group',
                $groupId,
                'store_view',
                $storeId,
                ['is_active' => $isActive]
            ));
        }

        $result->setSummaryValue('website_count', $websiteCount);
        $result->setSummaryValue('store_group_count', $storeGroupCount);
        $result->setSummaryValue('store_view_count', $storeViewCount);
        $result->setSummaryValue('active_store_view_count', $activeStoreViewCount);
    }

    /** @return array<int|string, mixed> */
    private function safeWebsites(ScanResult $result): array
    {
        try {
            return $this->storeManager->getWebsites(false);
        } catch (Throwable $throwable) {
            $result->addError(['collector' => self::class, 'message' => 'Unable to read websites.'], $throwable);
            return [];
        }
    }

    /** @return array<int|string, mixed> */
    private function safeGroups(ScanResult $result): array
    {
        try {
            return $this->storeManager->getGroups(false);
        } catch (Throwable $throwable) {
            $result->addError(['collector' => self::class, 'message' => 'Unable to read store groups.'], $throwable);
            return [];
        }
    }

    /** @return array<int|string, mixed> */
    private function safeStores(ScanResult $result): array
    {
        try {
            return $this->storeManager->getStores(false);
        } catch (Throwable $throwable) {
            $result->addError(['collector' => self::class, 'message' => 'Unable to read store views.'], $throwable);
            return [];
        }
    }

    private function safeCall(object $object, string $method): mixed
    {
        try {
            if (!method_exists($object, $method)) {
                return null;
            }

            return $object->{$method}();
        } catch (Throwable) {
            return null;
        }
    }
}
