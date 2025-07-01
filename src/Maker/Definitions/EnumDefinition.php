<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use JetBrains\PhpStorm\Pure;
use Ufo\RpcSdk\Maker\StringTransformerEnum;

class EnumDefinition
{

    const string FOLDER = 'Enums';

    protected array $values = [];

    public function __construct(
        protected string $namespace,
        protected string $enumName,
        protected string $type,
        array $values,
    )
    {
        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $key = StringTransformerEnum::transformName($value);
            }
            $this->values[$key] = $value;
        }
    }

    public function enumWithNamespace(): string
    {
        return static::FOLDER . '\\' . $this->enumName;
    }

    /**
     * @return array
     */
    public function getCases(): array
    {
        return $this->values;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getEnumName(): string
    {
        return $this->enumName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    #[Pure] public function getFullName(): string
    {
        return $this->getNamespace() . '\\' . $this->getEnumName();
    }

}
