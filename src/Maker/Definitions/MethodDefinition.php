<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function implode;
use function str_pad;

use const PHP_EOL;

class MethodDefinition
{
    /**
     * @var ArgumentDefinition[]
     */
    protected array $arguments = [];
    protected array $returns = [];

    protected static array $typesExclude = [];

    protected ?string $returnsDoc = null;

    protected array $uses = [];

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
     * @param string $typeExclude
     */
    public static function addTypeExclude(string $typeExclude): void
    {
        self::$typesExclude[$typeExclude] = $typeExclude;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param bool $withAttr
     * @return string
     */
    public function getArgumentsSignature(bool $withAttr = false): string
    {
        $args = [];
        foreach ($this->arguments as $name => $argument) {
            $args[$name] = '';
            if ($withAttr && count($argument->getAssertions()) > 0) {
                $args[$name] .= PHP_EOL . $argument->getAssertions()->getSignature() . str_pad('', 8);
                $this->uses[] = $argument->getAssertions()->getClass();
                $this->uses = array_merge($this->uses, $argument->getAssertions()->getAssertionsClasses());
            }
            $args[$name] .= $argument->getType() . ' $' . $name;
            if ($argument->isOptional()) {
                $value = ParamToStringConverter::defaultValue($argument->getDefaultValue());
                $args[$name] .= ' = ' . $value;
            }
        }
        $br = $withAttr ? PHP_EOL: '';
        return implode(', ' . $br . str_pad('', 8) , $args) . $br . ($withAttr? str_pad('', 4) : '');
    }

    /**
     * @return array
     */
    public function getUses(): array
    {
        return array_unique($this->uses);
    }

    public static function normalizeType(string $type): string
    {
        return self::$typesExclude[$type] ?? match ($type) {
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
     * @return ?string
     */
    public function getReturnsDoc(): ?string
    {
        return $this->returnsDoc;
    }

    /**
     * @param array|string $returns
     * @param string|null $returnsDoc
     * @return void
     */
    public function setReturns(array|string $returns, ?string $returnsDoc = null): void
    {
        if (is_array($returns)) {
            $this->returns = array_unique(array_map(function ($v) {
                return MethodDefinition::normalizeType($v);
            }, $returns));
        } else {
            $this->returns = [MethodDefinition::normalizeType($returns)];
        }
        if (is_null($returnsDoc)) {
            $this->returnsDoc = implode('|', $this->returns);
        } else {
            $this->returnsDoc = $returnsDoc;
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
