<?php

namespace Ufo\RpcSdk\Maker\Traits;

use function is_array;

trait ClassDefinitionsMethodsHolderTrait
{
    protected string $namespace;
    protected string $className;
    /**
     * @var array ['name' => 'type']
     */
    protected array $properties = [];

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getShortName(): string
    {
        return $this->className;
    }

    public function getFQCN(): string
    {
        return $this->getNamespace() . '\\' . $this->getShortName();
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function setProperties(array $properties): void
    {
        if (isset($properties[0]) && is_array($properties[0])) {
            $properties = $properties[0];
        }
        $this->properties = $properties;
    }
}