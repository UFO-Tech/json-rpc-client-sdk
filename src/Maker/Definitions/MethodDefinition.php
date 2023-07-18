<?php

namespace Ufo\RpcSdk\Maker\Definitions;

class MethodDefinition
{
    /**
     * @var ArgumentDefinition[]
     */
    protected array $arguments = [];
    protected array $returns = [];

    /**
     * @param string $name
     * @param string $apiProcedure
     */
    public function __construct(
        protected string $name,
        protected string $apiProcedure,
    )
    {
    }

    public function addArgument(ArgumentDefinition $argument): void
    {
        $this->arguments[$argument->getName()] = $argument;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return string
     */
    public function getArgumentsSignature(): string
    {
        $args = [];
        foreach ($this->arguments as $name => $argument) {
            $args[$name] = $argument->getType() . ' $' . $name;
            if ($argument->isOptional()) {
                $value = $argument->getDefaultValue();
                if (is_null($value)) {
                    $value = 'null';
                } elseif($value === '') {
                    $value = '""';
                }
                $args[$name] .= ' = ' . $value;
            }
        }
        return implode(', ', $args);
    }
    public static function normalizeType(string $type): string
    {
        return match ($type) {
            'any', 'mixed' => 'mixed',
            'arr', 'array' => 'array',
            'boolean', 'true', 'false' => 'bool',
            'dbl', 'double', 'float' => 'float',
            'integer', 'int' => 'int',
            'nil', 'null', 'void' => 'null',
            'string', 'str' => 'string',
            default   => 'object'
        };
    }
    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getReturns(): array
    {
        return $this->returns;
    }

    /**
     * @param array|string $returns
     */
    public function setReturns(array|string $returns): void
    {
        if (is_array($returns)) {
            $this->returns = array_unique(array_map(function ($v) {
                return MethodDefinition::normalizeType($v);
            }, $returns));
        } else {
            $this->returns = [MethodDefinition::normalizeType($returns)];
        }
    }

    /**
     * @return string
     */
    public function getApiProcedure(): string
    {
        return $this->apiProcedure;
    }

}
