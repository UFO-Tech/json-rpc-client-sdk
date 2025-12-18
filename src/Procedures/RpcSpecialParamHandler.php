<?php

namespace Ufo\RpcSdk\Procedures;

use Ufo\RpcObject\IRpcSpecialParamHandler;
use Ufo\RpcObject\SpecialRpcParams;
use Ufo\RpcObject\SpecialRpcParamsEnum;

class RpcSpecialParamHandler implements IRpcSpecialParamHandler
{
    protected SpecialRpcParams $specialRpcParams;

    protected array $specialParams = [];

    public function setParam(string $name, mixed $value): static
    {
        $this->specialParams[$name] = $value;
        return $this;
    }

    public function resetParams(): static
    {
        $this->specialParams = [];
        return $this;
    }

    public function getSpecialParams(): array
    {
        $this->specialRpcParams = SpecialRpcParamsEnum::fromArray($this->specialParams);
        return $this->specialRpcParams->toArray();
    }

}