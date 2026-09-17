<?php

namespace Ufo\RpcSdk\Tests\Procedures;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ufo\RpcSdk\Exceptions\ConfigNotFoundException;
use Ufo\RpcSdk\Procedures\SdkConfigs;

class SdkConfigsTest extends TestCase
{
    public static function endpoints(): iterable
    {
        yield 'named async' => [['Api' => ['rpc_async_kafka' => 'kafka://broker/events']], [], 'rpc_async_kafka', 'kafka://broker/events'];
        yield 'named sync' => [['Api' => ['sync_internal' => 'https://internal/api']], [], 'sync_internal', 'https://internal/api'];
        yield 'dist fallback' => [[], ['Api' => ['sync' => 'https://dist/api']], 'sync', 'https://dist/api'];
        yield 'local override' => [['Api' => ['sync' => 'https://local/api']], ['Api' => ['sync' => 'https://dist/api']], 'sync', 'https://local/api'];
        yield 'string endpoint compatibility' => [['Api' => 'https://legacy/api'], [], 'sync', 'https://legacy/api'];
    }

    #[DataProvider('endpoints')]
    public function testResolvesEndpoint(array $local, array $dist, string $name, string $expected): void
    {
        self::assertSame($expected, $this->configs($local, $dist)->getApiEndpoint('Api', $name));
    }

    public static function missingEndpoints(): iterable
    {
        yield 'missing vendor' => [[]];
        yield 'missing named transport' => [['Api' => ['sync' => 'https://example.test']]];
        yield 'legacy async key is rejected' => [['Api' => ['async' => 'amqp://broker']]];
    }

    #[DataProvider('missingEndpoints')]
    public function testMissingEndpointThrows(array $local): void
    {
        $this->expectException(ConfigNotFoundException::class);
        $this->configs($local, [])->getApiEndpoint('Api', 'rpc_async');
    }

    private function configs(array $local, array $dist): SdkConfigs
    {
        $configs = $this->getMockBuilder(SdkConfigs::class)->disableOriginalConstructor()->onlyMethods(['getConfigs'])->getMock();
        $configs->method('getConfigs')->willReturnCallback(static fn(bool $isDist = false): array => $isDist ? $dist : $local);
        return $configs;
    }
}
