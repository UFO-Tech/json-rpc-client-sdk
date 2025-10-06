<?php

namespace Ufo\RpcSdk\Maker\Interfaces;

interface IMaker
{
    /**
     * @param null|callable(IClassLikeDefinition): IClassLikeDefinition $callbackOutput
     * @return void
     */
    public function make(?callable $callbackOutput = null): void;

}