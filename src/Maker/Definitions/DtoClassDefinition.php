<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Ufo\DTO\Helpers\TypeHintResolver;

use function end;
use function explode;
use function implode;

class DtoClassDefinition extends ClassDefinition
{
    const string FOLDER = 'DTO';

    protected array $docs = [];

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

    public static function dtoWithNamespace(string $dtoName, bool $collection = false): string
    {
        return static::FOLDER . '\\' . $dtoName . ($collection ? '[]' : '');
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getDocs(): array
    {
        return $this->docs;
    }

    /**
     * @param array $properties
     */
    public function setProperties(array $properties): void
    {
        foreach ($properties as $name => $schema) {
            if ($schema['$ref'] ?? false) {
                $parts = explode('/', $schema['$ref']);

                $this->properties[$name] = end($parts);
                continue;
            }

            if ($schema['oneOf'] ?? false) {
                $propertyTypes = [];
                $docTypes = [];
                foreach ($schema['oneOf'] as $type) {
                    if ($type['$ref'] ?? false) {
                        $parts = explode('/', $type['$ref']);
                        $realType = end($parts);
                    } else {
                        $realType = TypeHintResolver::jsonSchemaToPhp($type['type']);
                    }
                    $propertyTypes[] = $realType;
                    $docTypes[] = $this->getItems($realType, $type);
                }
                if ($docTypes !== $propertyTypes) {
                    $this->docs[$name] = implode('|', $docTypes);
                }
                $this->properties[$name] = implode('|', $propertyTypes);
            } else {
                $realType = TypeHintResolver::jsonSchemaToPhp($schema);
                $docType = $this->getItems($realType, $schema);
                if ($docType !== $realType) {
                    $this->docs[$name] = $docType;
                }
                $this->properties[$name] = TypeHintResolver::jsonSchemaToPhp($schema);
            }

        }
    }

    protected function getItems(string $type, array $schema): string
    {
        $res = $type;
        if ($type === 'array' && ($schema['items']['$ref'] ?? false)) {
            $parts = explode('/', $schema['items']['$ref']);
            $res = end($parts) . '[]';
        }
        return $res;
    }
}
