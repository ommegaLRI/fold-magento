<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\StoreGraph;

use InvalidArgumentException;

final class Entity
{
    private string $type;
    private string $id;
    private ?string $label;
    /** @var array<string, mixed> */
    private array $attributes;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(string $type, string $id, ?string $label = null, array $attributes = [])
    {
        $type = trim($type);
        $id = trim($id);

        if ($type === '') {
            throw new InvalidArgumentException('StoreGraph entity type cannot be empty.');
        }

        if ($id === '') {
            throw new InvalidArgumentException('StoreGraph entity ID cannot be empty.');
        }

        $this->type = $type;
        $this->id = $id;
        $this->label = $label !== null && trim($label) !== '' ? trim($label) : null;
        $this->attributes = $attributes;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => 'entity',
            'type' => $this->type,
            'id' => $this->id,
            'label' => $this->label,
            'attributes' => $this->attributes,
        ];
    }
}
