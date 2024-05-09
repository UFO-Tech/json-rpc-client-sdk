<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Ufo\RpcSdk\Exceptions\SdkBuilderException;

class ArgumentDefinition
{
    protected string $type;

    /**
     * @param string $name
     * @param string|array $type
     * @param bool $optional
     * @param mixed|null $defaultValue
     */
    public function __construct(
        protected string $name,
        string|array $type,
        protected bool $optional,
        protected mixed $defaultValue = null
    )
    {
        if (is_array($type)) {
            $type = array_map(function ($v) {
                return MethodDefinition::normalizeType($v);
            }, $type);
            $this->type = implode('|', $type);
        } else {
            $this->type = MethodDefinition::normalizeType($type);
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->optional;
    }

    /**
     * @return mixed
     */
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }
}
