<?php

namespace Ufo\RpcSdk\Maker\Traits;

use Ufo\RpcSdk\Exceptions\SdkBuilderException;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeDefinition;

trait ClassLikeStackHolderTrait
{
    /**
     * @var IClassLikeDefinition[]
     */
    protected array $classStack = [];

    public function getStack(): array
    {
        return $this->classStack;
    }

    public function getFromStack(string $key): IClassLikeDefinition
    {
        return $this->classStack[$key] ?? throw new SdkBuilderException();
    }

    public function addToStack(IClassLikeDefinition $definition, ?string $key = null): static
    {
        $this->classStack[$key ?? $definition->getShortName()] = $definition;
        return $this;
    }
}
