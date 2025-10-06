<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use ReflectionClass;
use Ufo\RpcError\WrongWayException;

use function class_exists;
use function end;
use function explode;
use function implode;
use function str_pad;

use const PHP_EOL;

abstract class AttributeDefinition
{
    protected array $constructorProperties = [];

    public function __construct(protected string $classFQCN, protected array $context = [])
    {
        if (!class_exists($this->classFQCN)) {
            throw new WrongWayException();
        }
        $reflection = new ReflectionClass($this->classFQCN);
        foreach ($reflection->getConstructor()->getParameters() as $paramReflection) {
            $this->constructorProperties[$paramReflection->getName()] = $paramReflection;
        }
    }

    public function getClassFQCN(): string
    {
        return $this->classFQCN;
    }

    abstract protected function buildSignature(int $tab = 2): array;

    public function getSignature(int $tab = 2): string
    {
        $signature = str_pad(' ', $tab * 4);
        $signature .= '#[';
        $signature .= $this->getShortName();

        $specific = $this->buildSignature($tab + 1);
        $signature .= count($specific) > 0 ? '(' : '';
        $signature .= implode(', ', $specific);
        $signature .= count($specific) > 0 ? ')' : '';

        $signature .= ']' . PHP_EOL;
        return $signature;
    }

    public function __toString(): string
    {
        return $this->getSignature();
    }

    public function getShortName(): string
    {
        $n = explode('\\', $this->classFQCN);
        return end($n);
    }
}