<?php

namespace Ufo\RpcSdk\Maker\Traits;

use Ufo\RpcSdk\Maker\Helpers\ParamToStringConverter;

use function is_array;

trait ClassDefinitionsMethodsHolderTrait
{
    protected string $namespace;
    protected string $className;
    /**
     * @var array<string, string> ['name' => 'type']
     */
    protected array $properties = [];

    /**
     * @var array<string, string> ['name' => 'value']
     */
    protected array $defaultValues = [];

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

    public function getDefaultValues(): array
    {
        return $this->defaultValues;
    }

    public function addDefaultValue(string $name, mixed $defaultValue, ?string $type = null): static
    {
        $this->defaultValues[$name] = ' = ' . ParamToStringConverter::defaultValue($defaultValue, $type);

        return $this;
    }
}