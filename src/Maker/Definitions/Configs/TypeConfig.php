<?php

namespace Ufo\RpcSdk\Maker\Definitions\Configs;

use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver;

use function str_contains;

readonly class TypeConfig
{
    public function __construct(
        public array $schema,
        public string $type,
        public ?string $typeDoc = null,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $result, array $namespaces = []) :static
    {
        $schema = $result['schema'] ?? [];
        return new static(
            $schema,
            $schema ? TypeHintResolver::jsonSchemaToPhp($schema, $namespaces) : TypeHintResolver::MIXED->value,
            $schema ? TypeHintResolver::jsonSchemaToTypeDescription($schema, $namespaces) : null,
            (!empty($result['description']) ? $result['description'] : null),
        );
    }

    protected function schemaHasDto(): bool
    {
        $res = false;
        TypeHintResolver::filterSchema($this->schema, function ($schema) use (&$res) {
            if ($schema[TypeHintResolver::REF] ?? false) $res = true;
        });

        return $res;
    }

    public function hasDto(): bool
    {
        return $this->schemaHasDto() && !str_contains(TypeHintResolver::ARRAY->value, $this->type);
    }

    public function hasDtoCollection(): bool
    {
        return $this->schemaHasDto() && str_contains(TypeHintResolver::ARRAY->value, $this->type);
    }

    public function hasEnum(): bool
    {
        return EnumResolver::schemaHasEnum($this->schema);
    }

}