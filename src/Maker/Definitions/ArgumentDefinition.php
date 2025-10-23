<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Ufo\RpcError\RpcDataNotFoundException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcSdk\Maker\Definitions\Configs\TypeConfig;
use Ufo\RpcSdk\Maker\Helpers\DocHelper;

class ArgumentDefinition
{
    protected AssertionsDefinition $assertions;

    protected mixed $defaultValue = null;

    /**
     * @throws WrongWayException|RpcDataNotFoundException
     */
    public function __construct(
        protected string $name,
        protected TypeConfig $typeConfig,
        protected bool $optional,
        ?string $assertions = null,
        mixed $defaultValue = null,
    )
    {
        $this->defaultValue = $defaultValue ?? DocHelper::getPath($typeConfig->schema, 'default', strict: false);
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
        return $this->typeConfig->type;
    }

    public function getTypeDescription(): string
    {
        return $this->typeConfig->typeDoc ?? $this->getType();
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
