<?php

declare(strict_types=1);

namespace Ufo\RpcSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufo\RpcSdk\Maker\Definitions\ArgumentDefinition;
use Ufo\RpcSdk\Maker\Definitions\ClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\MethodDefinition;

class ClassDefinitionTest extends TestCase
{
    public function testCreateSuccess()
    {
        $classDefinition = new ClassDefinition('App', 'SuperClass');

        $this->assertEquals('App', $classDefinition->getNamespace());
        $this->assertEquals('SuperClass', $classDefinition->getClassName());

        $this->assertEquals('App\SuperClass', $classDefinition->getFullName());

        $this->assertEmpty($classDefinition->getProperties());
        $this->assertEmpty($classDefinition->getMethods());
    }

    public function testAddPropsAndMethods()
    {
        $classDefinition = new ClassDefinition('App', 'SuperClass');

        $method = new MethodDefinition('do', '');
        $arg = new ArgumentDefinition('arg1', 'string', false);
        $method->addArgument($arg);

        $classDefinition->addMethod($method);

        $this->assertNotEmpty($classDefinition->getMethods());
        $classDefinition->setProperties(['props' => 'string']);

        $this->assertNotEmpty($classDefinition->getProperties());
    }
}