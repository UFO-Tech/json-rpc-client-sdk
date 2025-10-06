<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Ufo\DTO\VO\EnumVO;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeDefinition;
use Ufo\RpcSdk\Maker\Traits\ClassDefinitionsMethodsHolderTrait;

class EnumDefinition implements IClassLikeDefinition
{
    use ClassDefinitionsMethodsHolderTrait;

    const string TYPE_CLASS = 'enum';
    const string FOLDER = 'Enums';

    protected array $values = [];

    public function __construct(
        string $namespace,
        protected EnumVO $enumConfig,
    )
    {
        $this->className = $enumConfig->name;
        $this->namespace = $namespace;
    }

    /**
     * @return array
     */
    public function getCases(): array
    {
        return $this->enumConfig->values;
    }

    public function getType(): string
    {
        return $this->enumConfig->type->value;
    }
}
