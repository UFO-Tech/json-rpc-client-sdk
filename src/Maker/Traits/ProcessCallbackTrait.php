<?php

namespace Ufo\RpcSdk\Maker\Traits;

use Ufo\RpcSdk\Maker\Interfaces\IClassLikeDefinition;

trait ProcessCallbackTrait
{
    abstract public function addToStack(IClassLikeDefinition $definition, ?string $key = null): static;

    protected function processCallback(
        IClassLikeDefinition $classDefinition,
        ?callable $callbackOutput = null,
    ): IClassLikeDefinition
    {
        if ($callbackOutput) {
            $returnDefinition = $callbackOutput($classDefinition);
            if ($returnDefinition instanceof IClassLikeDefinition) {
                $classDefinition = $returnDefinition;
            }
        }
        $this->addToStack($classDefinition);
        return $classDefinition;
    }
}