<?php

namespace Ufo\RpcSdk\Maker\Interfaces;

use Ufo\RpcSdk\Exceptions\SdkBuilderException;

interface IClassLikeStackHolder
{

    /**
     * @return IClassLikeDefinition[]
     */
    public function getStack(): array;

    /**
     * @throws SdkBuilderException
     */
    public function getFromStack(string $key): IClassLikeDefinition;

    public function addToStack(IClassLikeDefinition $definition, ?string $key = null): static;
}