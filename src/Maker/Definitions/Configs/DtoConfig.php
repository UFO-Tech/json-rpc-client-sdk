<?php

namespace Ufo\RpcSdk\Maker\Definitions\Configs;


use function array_map;

readonly class DtoConfig
{
    /**
     * @param ParamConfig[] $params
     */
    public function __construct(
        public string $name,
        public array $params,
    ) {}

    public static function fromArray(string $paramName, array $properties) :static
    {
        return new static(
            $paramName,
            array_map(
                fn(array $property) => ParamConfig::fromArray($property, false),
                $properties
            )
        );
    }

}
