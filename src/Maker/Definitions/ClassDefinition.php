<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use JetBrains\PhpStorm\Pure;

class ClassDefinition
{
    /**
     * @var MethodDefinition[]
     */
    protected array $methods;

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
