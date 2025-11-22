<?php

namespace Maker\Definitions\Configs;

use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use PHPUnit\Framework\TestCase;
use Ufo\RpcSdk\Maker\DocReader\Interfaces\IDocReader;
use Ufo\RpcSdk\Exceptions\SdkBuilderException;

class ConfigsHolderTest extends TestCase
{
    private function createConfigHolder(array $ignored): ConfigsHolder
    {
        $docReader = $this->createMock(IDocReader::class);
        $docReader->method('getApiDocumentation')->willReturn([
            'openrpc'    => '1.2.6',
            'servers'    => [
                [
                    'url'   => 'http://example.test',
                    '_core' => [
                        'envelop'   => 'UFO-RPC-2',
                        'transport' => [
                            'sync'  => [],
                            'async' => [],
                        ],
                    ],
                ],
            ],
            'components' => ['schemas' => []],
            'procedures' => [],
        ]);

        return new ConfigsHolder(
            docReader: $docReader,
            projectRootDir: '/tmp',
            apiVendorAlias: 'test',
            ignoredMethods: $ignored
        );
    }

    // -----------------------------------------------------
    //  BASIC MASK TESTS
    // -----------------------------------------------------
    public function testIgnoreSimpleMask(): void
    {
        $config = $this->createConfigHolder(['User.create']);
        self::assertTrue($config->methodShouldIgnore('User.create'));
        self::assertFalse($config->methodShouldIgnore('User.update'));
    }

    public function testIgnoreWildcardMask(): void
    {
        $config = $this->createConfigHolder(['*.delete']);
        self::assertTrue($config->methodShouldIgnore('User.delete'));
        self::assertTrue($config->methodShouldIgnore('Comment.delete'));
        self::assertFalse($config->methodShouldIgnore('User.update'));
    }

    public function testIgnoreAllMask(): void
    {
        $config = $this->createConfigHolder(['*', '*.*', '!User.create', '!&User.update', '!~User.send']);
        self::assertTrue($config->methodShouldIgnore('User.delete'));
        self::assertTrue($config->methodShouldIgnore('Comment.delete'));
        self::assertFalse($config->methodShouldIgnore('User.create'));
        self::assertFalse($config->methodShouldIgnore('User.update'));;
        self::assertFalse($config->methodShouldIgnore('User.send', true));;
    }

    public function testInverseMask(): void
    {
        $config = $this->createConfigHolder(['*.delete', '!Comment.delete']);
        self::assertTrue($config->methodShouldIgnore('User.delete'));     // ігноруємо
        self::assertFalse($config->methodShouldIgnore('Comment.delete')); // примусово генеруємо
    }

    // -----------------------------------------------------
    //  SYNC / ASYNC TESTS
    // -----------------------------------------------------
    public function testSyncOnlyMask(): void
    {
        $config = $this->createConfigHolder(['&User.get']);
        self::assertTrue($config->methodShouldIgnore('User.get', async: false)); // SYNC
        self::assertFalse($config->methodShouldIgnore('User.get', async: true)); // ASYNC
    }

    public function testAsyncOnlyMask(): void
    {
        $config = $this->createConfigHolder(['~User.get']);
        self::assertTrue($config->methodShouldIgnore('User.get', async: true));
        self::assertFalse($config->methodShouldIgnore('User.get', async: false));
    }

    public function testInverseOnAsync(): void
    {
        $config = $this->createConfigHolder(['*.delete', '!~Comment.delete']);
        self::assertTrue($config->methodShouldIgnore('Comment.delete', async: false)); // sync блокується
        self::assertFalse($config->methodShouldIgnore('Comment.delete', async: true)); // async дозволено
    }

    // -----------------------------------------------------
    //  INVALID MASKS
    // -----------------------------------------------------
    public function testThrowsOnTwoSymbols(): void
    {
        $this->expectException(SdkBuilderException::class);
        $config = $this->createConfigHolder(['~&User.get']);
        $config->methodShouldIgnore('User.get');
    }

}

