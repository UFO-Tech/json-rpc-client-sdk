<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use JetBrains\PhpStorm\Pure;

class ClassDefinition
{
    /**
     * @var MethodDefinition[]
     */
    protected array $methods = [];

    /**
     * @var array ['name' => 'type']
     */
    protected array $properties = [];

    /**
     * @param string $namespace
     * @param string $className
     */
    public function __construct(protected string $namespace, protected string $className)
    {
    }

    /**
     * @return MethodDefinition[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @param MethodDefinition $method
     */
    public function addMethod(MethodDefinition $method): void
    {
        $this->methods[$method->getName()] = $method;
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @param array $properties
     */
    public function setProperties(array $properties): void
    {
        if (isset($properties[0]) && is_array($properties[0])) {
            $properties = $properties[0];
        }
        $this->properties = $properties;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    #[Pure] public function getFullName(): string
    {
        return $this->getNamespace() . '\\' . $this->getClassName();
    }
}
