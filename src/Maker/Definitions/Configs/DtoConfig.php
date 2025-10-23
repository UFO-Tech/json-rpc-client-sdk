<?php

namespace Ufo\RpcSdk\Maker\Definitions\Configs;


use function array_map;

readonly class DtoConfig
{
    /**
     * @var ParamConfig[]
     */
    public array $params;

    public function __construct(
        public string $name,
        array $params,
    )
    {
        $this->params =  array_map(
            fn(array $property) => ParamConfig::fromArray($this, $property, false),
            $params
        );
    }

    public static function fromArray(string $paramName, array $properties) :static
    {
        return new static(
            $paramName,
            $properties
        );
    }

}
