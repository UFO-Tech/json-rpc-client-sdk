<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use JetBrains\PhpStorm\Pure;
use Ufo\RpcObject\Helpers\TypeHintResolver;

use function array_map;
use function array_merge;
use function array_unique;

class DtoClassDefinition extends ClassDefinition
{
    /**
     * @param string $namespace
     * @param string $className
     */
    public function __construct(
        protected string $namespace,
        protected string $className
    )
    {
        parent::__construct($this->namespace, $this->className);
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
        foreach ($properties as $name => $schema) {
            $this->properties[$name] = TypeHintResolver::jsonSchemaToPhp($schema);
        }
    }

}
