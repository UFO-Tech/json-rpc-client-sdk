<?php

namespace Ufo\RpcSdk\Maker\Definitions\Configs;


use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\EnumDefinition;

use function strtolower;

class ParamConfig
{
    public function __construct(
        readonly public DtoConfig|ProcedureConfig $parentConfig,
        readonly public string $name,
        readonly public TypeConfig $typeConfig,
        readonly public bool $required = true,
        public ?string $assertions = null,
        public mixed $defaultValue = null,
    ) {}

    public static function fromArray(DtoConfig|ProcedureConfig $parentConfig, array $param, bool $inProcedure = true) :static
    {
        $namespaces = [
            strtolower(EnumDefinition::TYPE_CLASS) => EnumDefinition::FOLDER,
        ];
        if ($inProcedure) {
            $namespaces[strtolower(DtoClassDefinition::TYPE_CLASS)] = DtoClassDefinition::FOLDER;
        }
        return new static(
            parentConfig: $parentConfig,
            name: $param['name'],
            typeConfig: TypeConfig::fromArray($param, $namespaces),
            required: $param['required'] ?? true,
            assertions: $param['x-ufo-assertions'] ?? null,
            defaultValue: $param['schema']['default'] ?? null,
        );
    }

}
