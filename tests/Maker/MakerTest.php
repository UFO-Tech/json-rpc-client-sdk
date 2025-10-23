<?php

namespace Ufo\RpcSdk\Tests\Maker;

use PHPUnit\Framework\TestCase;
use Ufo\RpcSdk\Maker\Maker;
use Symfony\Bundle\MakerBundle\Generator;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\Interfaces\IMaker;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeDefinition;

class MakerTest extends TestCase
{
    public function testMakeCallsAllMakersAndStoresClasses(): void
    {
        $configsHolder = $this->createMock(ConfigsHolder::class);
        $generator = $this->createMock(Generator::class);

        $classDef = $this->createMock(IClassLikeDefinition::class);
        $classDef->method('getShortName')->willReturn('TestClass');

        $makerMock = $this->createMock(IMaker::class);
        $makerMock->expects($this->once())
                  ->method('make')
                  ->willReturnCallback(function ($callback) use ($classDef) {
                      $callback($classDef);
                  });

        $maker = new Maker($configsHolder, $generator, [$makerMock]);
        $maker->make(static fn ($def) => $def);

        $this->assertArrayHasKey('class', $maker->getClasses());
        $this->assertArrayHasKey('TestClass', $maker->getClasses()['class']);
        $this->assertSame($classDef, $maker->getClasses()['class']['TestClass']);
    }

    public function testDestructorWritesChanges(): void
    {
        $configsHolder = $this->createMock(ConfigsHolder::class);
        $generator = $this->createMock(Generator::class);
        $generator->expects($this->once())->method('writeChanges');

        $maker = new Maker($configsHolder, $generator);
        unset($maker);
    }
}
