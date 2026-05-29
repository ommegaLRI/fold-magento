<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\StoreGraph;

use InvalidArgumentException;

final class Relationship
{
    private string $type;
    private string $fromType;
    private string $fromId;
    private string $toType;
    private string $toId;
    /** @var array<string, mixed> */
    private array $attributes;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        string $type,
        string $fromType,
        string $fromId,
        string $toType,
        string $toId,
        array $attributes = []
    ) {
        foreach ([
            'relationship type' => $type,
            'from type' => $fromType,
            'from id' => $fromId,
            'to type' => $toType,
            'to id' => $toId,
        ] as $label => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('StoreGraph %s cannot be empty.', $label));
            }
        }

        $this->type = trim($type);
        $this->fromType = trim($fromType);
        $this->fromId = trim($fromId);
        $this->toType = trim($toType);
        $this->toId = trim($toId);
        $this->attributes = $attributes;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => 'relationship',
            'type' => $this->type,
            'from' => [
                'type' => $this->fromType,
                'id' => $this->fromId,
            ],
            'to' => [
                'type' => $this->toType,
                'id' => $this->toId,
            ],
            'attributes' => $this->attributes,
        ];
    }
}
