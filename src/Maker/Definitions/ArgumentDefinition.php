<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Ufo\RpcError\WrongWayException;
use Ufo\RpcSdk\Exceptions\SdkBuilderException;

class ArgumentDefinition
{
    protected AssertionsDefinition $assertions;

    /**
     * @param string $name
     * @param string $type
     * @param bool $optional
     * @param mixed|null $defaultValue
     * @param ?string $assertions
     */
    public function __construct(
        protected string $name,
        protected string $type,
        protected bool $optional,
        protected mixed $defaultValue = null,
        ?string $assertions = null
    )
    {
        $this->assertions = new AssertionsDefinition($assertions ?? '');
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

    /**
     * @return AssertionsDefinition
     */
    public function getAssertions(): AssertionsDefinition
    {
        return $this->assertions;
    }

}
