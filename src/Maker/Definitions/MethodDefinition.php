<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Ufo\RpcSdk\Maker\Definitions\Configs\ResultConfig;
use Ufo\RpcSdk\Maker\Helpers\ParamToStringConverter;

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
    protected string $returns = '';

    protected ResultConfig $resultConfig;

    protected static array $typesExclude = [];

    protected string $returnsDoc;
    protected string $returnsDesc;

    protected array $uses = [];

    public function __construct(
        protected string $name,
        protected string $apiProcedure,
    ) {}

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
                $this->uses[] = $argument->getAssertions()->getClassFQCN();
            }
            $type = $withAttr ? $argument->getType() : $argument->getTypeDescription();
            $args[$name] .= $type . ' $' . $name;
            if ($argument->isOptional()) {
                $value = ParamToStringConverter::defaultValue($argument->getDefaultValue());
                $args[$name] .= ' = ' . $value;
            }
        }
        $br = $withAttr ? PHP_EOL : '';

        return implode(', '.$br.str_pad('', 8) , $args)
               .$br
               .($withAttr ? str_pad('', 4) : '');
    }

    /**
     * @return array
     */
    public function getUses(): array
    {
        return array_unique($this->uses);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getReturns(): string
    {
        return $this->returns;
    }

    /**
     * @return string
     */
    public function getReturnsDoc(): string
    {
        return $this->returnsDoc;
    }

    public function setReturns(ResultConfig $returns): void
    {
        $this->resultConfig = $returns;
        $this->returns = $returns->type;
        $this->returnsDoc = ($returns->typeDoc ?? '');
        $this->returnsDesc = ($returns->typeDoc ?? '') . ' ' . ($returns->description ?? '');
    }

    public function getReturnsDesc(): string
    {
        return $this->returnsDesc;
    }

    public function getResultConfig(): ResultConfig
    {
        return $this->resultConfig;
    }

    /**
     * @return string
     */
    public function getApiProcedure(): string
    {
        return $this->apiProcedure;
    }

}
