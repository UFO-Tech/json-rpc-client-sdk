<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use JetBrains\PhpStorm\Pure;

use function array_map;
use function array_merge;
use function array_unique;

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
     * @param bool $async
     */
    public function __construct(
        protected string $namespace,
        protected string $className,
        readonly public bool $async = false
    ) {}

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

    public static function toUpperCamelCase($string): string
    {
        $words = preg_split('/[\s_\-]+/', $string);
        $upperCamelCase = array_map('ucfirst', $words);
        return implode('', $upperCamelCase);
    }

}
