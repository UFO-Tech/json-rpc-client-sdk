<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Ufo\RpcError\WrongWayException;
use Ufo\RpcSdk\Exceptions\SdkBuilderException;

class ArgumentDefinition
{
    protected string $type;

    protected AssertionsDefinition $assertions;

    /**
     * @param string $name
     * @param string|array $type
     * @param bool $optional
     * @param mixed|null $defaultValue
     * @param array $assertions
     */
    public function __construct(
        protected string $name,
        string|array $type,
        protected bool $optional,
        protected mixed $defaultValue = null,
        array $assertions = []
    )
    {
        $this->assertions = new AssertionsDefinition();
        if (is_array($type)) {
            $type = array_map(function ($v) {
                return MethodDefinition::normalizeType($v);
            }, $type);
            $this->type = implode('|', $type);
        } else {
            $this->type = MethodDefinition::normalizeType($type);
        }
        foreach ($assertions as $assertion) {
            try {
                $this->assertions->addAssertion(new AssertionDefinition($assertion['class'], $assertion['context'] ?? []));
            } catch (WrongWayException) {
            }
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

    /**
     * @return AssertionsDefinition
     */
    public function getAssertions(): AssertionsDefinition
    {
        return $this->assertions;
    }

}
