<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Scanner;

use Magento\Config\Model\Config\Structure;
use Throwable;

final class ConfigSchemaCollector implements CollectorInterface
{
    private Structure $configStructure;

    public function __construct(Structure $configStructure)
    {
        $this->configStructure = $configStructure;
    }

    public function collect(ScanContext $context, ScanResult $result): void
    {
        unset($context);

        try {
            $sections = $this->asIterable($this->configStructure->getSections());
        } catch (Throwable $throwable) {
            $result->addError([
                'collector' => self::class,
                'message' => 'Unable to read Magento admin configuration structure.',
            ], $throwable);
            $result->setSummaryValue('config_schema_count', 0);
            return;
        }

        $count = 0;
        $sectionCount = 0;
        $pathsSeen = [];

        foreach ($sections as $section) {
            if (!is_object($section)) {
                continue;
            }

            $sectionId = $this->elementId($section);
            if ($sectionId === '') {
                continue;
            }

            $sectionCount++;
            $count += $this->walkChildren(
                $section,
                [$sectionId],
                $sectionId,
                $result,
                $pathsSeen,
                0
            );
        }

        $result->setSummaryValue('config_section_count', $sectionCount);
        $result->setSummaryValue('config_schema_count', $count);
    }

    /**
     * @param string[] $segments
     * @param array<string, true> $pathsSeen
     */
    private function walkChildren(
        object $element,
        array $segments,
        string $sectionId,
        ScanResult $result,
        array &$pathsSeen,
        int $depth
    ): int {
        $children = $this->children($element);
        $count = 0;

        foreach ($children as $child) {
            if (!is_object($child)) {
                continue;
            }

            $childId = $this->elementId($child);
            if ($childId === '') {
                continue;
            }

            $childSegments = array_merge($segments, [$childId]);
            $grandChildren = $this->children($child);

            if ($grandChildren !== []) {
                $count += $this->walkChildren($child, $childSegments, $sectionId, $result, $pathsSeen, $depth + 1);
                continue;
            }

            $path = $this->configPath($child, $childSegments);
            if ($path === '' || isset($pathsSeen[$path])) {
                continue;
            }

            $pathsSeen[$path] = true;
            $result->addConfigSchema($this->schemaRecord($child, $path, $sectionId, $childSegments, $depth));
            $count++;
        }

        return $count;
    }

    /** @return array<string, mixed> */
    private function schemaRecord(object $field, string $path, string $sectionId, array $segments, int $depth): array
    {
        return [
            'kind' => 'config_schema',
            'path' => $path,
            'section' => $sectionId,
            'group' => $segments[1] ?? null,
            'field' => end($segments) ?: null,
            'segments' => array_values($segments),
            'label' => $this->stringify($this->elementValue($field, ['getLabel'], ['label'])),
            'frontend_type' => $this->stringify($this->elementValue($field, ['getFrontendType'], ['frontend_type', 'type'])),
            'source_model' => $this->stringify($this->elementValue($field, ['getSourceModel'], ['source_model'])),
            'backend_model' => $this->stringify($this->elementValue($field, ['getBackendModel'], ['backend_model'])),
            'frontend_model' => $this->stringify($this->elementValue($field, ['getFrontendModel'], ['frontend_model'])),
            'comment' => $this->stringify($this->elementValue($field, ['getComment'], ['comment'])),
            'show_in_default' => $this->boolOrNull($this->elementValue($field, ['showInDefault'], ['showInDefault', 'show_in_default'])),
            'show_in_website' => $this->boolOrNull($this->elementValue($field, ['showInWebsite'], ['showInWebsite', 'show_in_website'])),
            'show_in_store' => $this->boolOrNull($this->elementValue($field, ['showInStore'], ['showInStore', 'show_in_store'])),
            'sort_order' => $this->elementValue($field, ['getSortOrder'], ['sort_order']),
            'depth' => $depth,
            'source' => 'system_config_structure',
        ];
    }

    private function configPath(object $field, array $segments): string
    {
        $explicit = $this->stringify($this->elementValue($field, ['getConfigPath'], ['config_path']));
        if ($explicit !== null && trim($explicit) !== '') {
            return trim($explicit);
        }

        return implode('/', array_map('strval', $segments));
    }

    /** @return array<int|string, mixed> */
    private function children(object $element): array
    {
        $children = $this->call($element, 'getChildren');
        return $this->asArray($children);
    }

    private function elementId(object $element): string
    {
        $id = $this->elementValue($element, ['getId'], ['id']);
        return trim((string) $id);
    }

    /**
     * @param string[] $methods
     * @param string[] $dataKeys
     */
    private function elementValue(object $element, array $methods, array $dataKeys): mixed
    {
        foreach ($methods as $method) {
            $value = $this->call($element, $method);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        foreach ($dataKeys as $key) {
            $value = $this->data($element, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function call(object $object, string $method): mixed
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

    private function data(object $object, string $key): mixed
    {
        try {
            if (!method_exists($object, 'getData')) {
                return null;
            }

            return $object->getData($key);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return iterable<int|string, mixed> */
    private function asIterable(mixed $value): iterable
    {
        if (is_iterable($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $value;
        }

        return [];
    }

    /** @return array<int|string, mixed> */
    private function asArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \Traversable) {
            return iterator_to_array($value);
        }

        return [];
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            try {
                return (string) $value;
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function boolOrNull(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (bool) $value;
    }
}
