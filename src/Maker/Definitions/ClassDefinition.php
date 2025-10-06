<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Ufo\RpcSdk\Maker\Interfaces\IClassLikeDefinition;
use Ufo\RpcSdk\Maker\Interfaces\IHaveMethodsDefinitions;
use Ufo\RpcSdk\Maker\Traits\ClassDefinitionsMethodsHolderTrait;

use function array_map;
use function array_merge;
use function array_unique;

class ClassDefinition implements IClassLikeDefinition, IHaveMethodsDefinitions
{
    use ClassDefinitionsMethodsHolderTrait;

    /**
     * @var MethodDefinition[]
     */
    protected array $methods = [];

    /**
     * @param string $namespace
     * @param string $className
     * @param bool $async
     */
    public function __construct(
        string $namespace,
        string $className,
        readonly public bool $async = false
    )
    {
        $this->className = $className;
        $this->namespace = $namespace;
    }

    /**
     * @return MethodDefinition[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getMethodsUses(): array
    {
        $uses = [];
        array_map(function(MethodDefinition $m) use (&$uses) {
            $m->getArgumentsSignature(true);
            $uses = array_merge($uses, $m->getUses());
        }, $this->methods);
        return array_unique($uses);
    }

    public function addMethod(MethodDefinition $method): static
    {
        $this->methods[$method->getName()] = $method;
        return $this;
    }
}
