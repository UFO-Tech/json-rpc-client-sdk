<?php

declare(strict_types=1);

namespace Ufo\RpcSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufo\RpcSdk\Maker\Definitions\ArgumentDefinition;
use Ufo\RpcSdk\Maker\Definitions\MethodDefinition;

class MethodDefinitionTest extends TestCase
{
    public function testCreateSuccess()
    {
        $methodDefinition = new MethodDefinition('doSomething', 'apiProcedure');

        $this->assertEquals('doSomething', $methodDefinition->getName());
        $this->assertEquals('apiProcedure', $methodDefinition->getApiProcedure());

        $this->assertEquals([], $methodDefinition->getArguments());
        $this->assertEquals([], $methodDefinition->getReturns());

        $this->assertNull($methodDefinition->getReturnsDoc());
    }

    public function testAddArgsSuccess()
    {
        $methodDefinition = new MethodDefinition('doSomething', 'apiProcedure');

        $arg1 = new ArgumentDefinition('arg1', 'float', false);
        $methodDefinition->addArgument($arg1);

        $arg2 = new ArgumentDefinition('arg2', ['int', 'null'], true, 999);
        $methodDefinition->addArgument($arg2);

        $this->assertIsArray($methodDefinition->getArguments());
        $this->assertCount(2, $methodDefinition->getArguments());

        $this->assertEquals('float $arg1, int|null $arg2 = 999', $methodDefinition->getArgumentsSignature());

        $this->assertEquals([], $methodDefinition->getReturns());
    }

    public function testAddArgsWithReturnsType()
    {
        $methodDefinition = new MethodDefinition('add', 'calc');
        $arg1 = new ArgumentDefinition('arg1', ['float', 'int'], false, 0);
        $arg2 = new ArgumentDefinition('arg2', ['float', 'int'], false, 0);

        $methodDefinition->addArgument($arg1);
        $methodDefinition->addArgument($arg2);

        $this->assertIsArray($methodDefinition->getArguments());
        $this->assertCount(2, $methodDefinition->getArguments());

        $this->assertEquals('float|int $arg1, float|int $arg2', $methodDefinition->getArgumentsSignature());

        $methodDefinition->setReturns(['float', 'int']);

        $this->assertNotEmpty($methodDefinition->getReturns());

        $this->assertEquals('float|int', $methodDefinition->getReturnsDoc());
    }
}
