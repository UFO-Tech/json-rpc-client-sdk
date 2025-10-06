<?php

namespace Ufo\RpcSdk\Maker\Definitions\Configs;


use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\EnumDefinition;

use function strtolower;

class ParamConfig
{
    public function __construct(
        readonly public string $name,
        readonly public TypeConfig $typeConfig,
        readonly public bool $required = true,
        public ?string $assertions = null
    ) {}

    public static function fromArray(array $param, bool $inProcedure = true) :static
    {
        $namespaces = [
            strtolower(EnumDefinition::TYPE_CLASS) => EnumDefinition::FOLDER,
        ];
        if ($inProcedure) {
            $namespaces[strtolower(DtoClassDefinition::TYPE_CLASS)] = DtoClassDefinition::FOLDER;
        }
        return new static(
            $param['name'],
            TypeConfig::fromArray($param, $namespaces),
            $param['required'] ?? true,
            $param['x-ufo-assertions'] ?? null,
        );
    }

}
