<?php

namespace Ufo\RpcSdk\Maker\Definitions\Configs;


use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\EnumDefinition;

use function array_map;
use function strtolower;

readonly class ProcedureConfig
{
    /**
     * @param array<string, string> $tags
     * @param ParamConfig[] $params
     */
    public function __construct(
        public string $name,
        public array $tags,
        public string $summary,
        public array $params,
        public ResultConfig $result,
        public array $context = []
    ) {}

    public static function fromArray(array $procedure) :static
    {
        $namespaces = [
            strtolower(EnumDefinition::TYPE_CLASS) => EnumDefinition::FOLDER,
            strtolower(DtoClassDefinition::TYPE_CLASS) => DtoClassDefinition::FOLDER
        ];
        return new static(
            $procedure['name'],
            $procedure['tags'] ?? [],
            $procedure['summary'] ?? '',
            array_map(fn(array $param) => ParamConfig::fromArray($param), $procedure['params'] ?? []),
            ResultConfig::fromArray($procedure['result'] ?? [], $namespaces),
        );
    }

}
