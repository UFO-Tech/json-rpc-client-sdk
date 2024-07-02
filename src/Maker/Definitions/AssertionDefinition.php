<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Range;
use Ufo\RpcError\WrongWayException;

use function class_exists;
use function count;
use function implode;
use function is_null;
use function str_pad;

use const PHP_EOL;

class AssertionDefinition extends AttributeDefinition
{
    const EXCLUSION = [
        Range::class => 'prepareRangeSignature'
    ];

    /**
     * @throws WrongWayException
     */
    public function __construct(string $class, array $context = [])
    {
        parent::__construct($class, $context);
    }

    public function getSignature(int $tab = 3): string
    {
        if (isset(static::EXCLUSION[$this->getClass()])) {
            $this->{static::EXCLUSION[$this->getClass()]}();
        }
        $signature = str_pad(' ', $tab * 4);
        $signature .= 'new ' . $this->getShortClassname() . '(';
        $signature .= implode(', ', $this->buildSignature());
        $signature .= ')';
        return $signature;
    }
    protected function buildSignature(): array
    {
        $specific = [];
        foreach ($this->constructorProperties as $pName => $pReflection) {
            if (isset($this->context[$pName])) {
                $specific[] = $pName . ': ' . ParamToStringConverter::convert($this->context[$pName]);
            }
        }

        return $specific;
    }

    protected function prepareRangeSignature(): void
    {
        if (!is_null($this->context['min']) && !is_null($this->context['max'])) {
            unset($this->context['minMessage']);
            unset($this->context['maxMessage']);
        }
    }
}