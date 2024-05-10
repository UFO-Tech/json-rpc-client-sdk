<?php

declare(strict_types=1);

namespace Ufo\RpcSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufo\RpcSdk\Maker\Definitions\ArgumentDefinition;

class ArgumentDefinitionTest extends TestCase
{
    public function testCreateSuccess(): void
    {
        $argumentDefinition = new ArgumentDefinition('arg1', 'string', false);

        $this->assertEquals('string', $argumentDefinition->getType());
        $this->assertEquals('arg1', $argumentDefinition->getName());
        $this->assertFalse($argumentDefinition->isOptional());

        $this->assertNull($argumentDefinition->getDefaultValue());

        $argumentDefinition = new ArgumentDefinition('arg1', 'str', false);
        $this->assertEquals('string', $argumentDefinition->getType());

        $argumentDefinition = new ArgumentDefinition('arg1', '123123', false);
        $this->assertEquals('object', $argumentDefinition->getType());

        $argumentDefinition = new ArgumentDefinition('arg1', ['string', 'null'], false, 'arg1');
        $this->assertEquals('string|null', $argumentDefinition->getType());
        $this->assertEquals('arg1', $argumentDefinition->getDefaultValue());

        $this->assertFalse($argumentDefinition->isOptional());
    }
}
