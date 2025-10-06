<?php

namespace Ufo\RpcSdk\Maker\Interfaces;

use Ufo\RpcSdk\Maker\Definitions\MethodDefinition;

interface IHaveMethodsDefinitions
{
    public function getMethods(): array;
    public function getMethodsUses(): array;
    public function addMethod(MethodDefinition $method): static;
}