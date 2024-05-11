<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Countable;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Assertions;

use function array_keys;
use function count;
use function implode;
use function str_pad;

use const PHP_EOL;

class AssertionsDefinition extends AttributeDefinition implements Countable
{
    /**
     * @var AssertionDefinition[]
     */
    protected array $assertions = [];
    /**
     * @throws WrongWayException
     */
    public function __construct()
    {
        parent::__construct(Assertions::class);
    }

    public function addAssertion(AssertionDefinition $assertion): static
    {
        $this->assertions[$assertion->getClass()] = $assertion;

        return $this;
    }

    protected function buildSignature(): array
    {
        $specific = [];
        foreach ($this->assertions as $assertion) {
            $specific[] = $assertion->getSignature();
        }
        return ['[' . PHP_EOL . implode(', ' . PHP_EOL, $specific) . PHP_EOL . str_pad(' ', 8) . ']'];
    }

    public function getAssertionsClasses(): array
    {
        return array_keys($this->assertions);
    }

    public function count(): int
    {
        return count($this->assertions);
    }

}