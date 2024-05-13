<?php

declare(strict_types=1);

namespace Ufo\RpcSdk\Tests\Unit;

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Ufo\RpcSdk\Maker\Maker;

class MakerTest extends TestCase
{
    private CacheInterface $mockCache;

    private const VENDOR_ALIAS = 'testVendor';

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->mockCache = $this->createMock(CacheInterface::class);
    }

    /**
     * @throws Exception
     */
    public function testCreateSuccess()
    {
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn($this->getJsonApiResponse());

        $maker = new Maker(
            apiUrl: $apiUrl = 'http://test.vendor/api',
            apiVendorAlias: self::VENDOR_ALIAS,
            namespace: 'Ufo\RpcSdk\Tests',
            cache: $this->mockCache,
        );

        $this->assertInstanceOf(Maker::class, $maker);
        $this->assertEquals($apiUrl, $maker->getApiUrl());
        $this->assertEquals(ucfirst(self::VENDOR_ALIAS), $maker->getApiVendorAlias());

        $this->assertEquals($this->getRpcProcedures(), $maker->getRpcProcedures());
    }

    public static function getJsonApiResponse(): array
    {
        return [
            "transport" => "POST",
            "envelope" => "JSON-RPC-2.0/UFO-RPC-5",
            "contentType" => "application/json",
            "description" => "",
            "target" => "/api",
            "id" => "",
            "services" => static::getRpcProcedures(),
        ];
    }

    public static function getRpcProcedures(): array
    {
        return [
            "TestProcedure.test" => [
                "envelope" => "JSON-RPC-2.0/UFO-RPC-5",
                "transport" => "POST",
                "name" => "TestProcedure.test",
                "description" => "",
                "parameters" => [
                    "email" => [
                        "type" => "string",
                        "name" => "email",
                        "description" => null,
                        "optional" => false,
                    ],
                    "name" => [
                        "type" => "string",
                        "name" => "name",
                        "description" => null,
                        "optional" => false,
                    ],
                    "token" => [
                        "type" => [
                            "string",
                            "null",
                        ],
                        "name" => "token",
                        "description" => null,
                        "optional" => true,
                        "default" => null,
                    ],
                ],
                "json_schema" => [],
                "returns" => "object",
                "responseFormat" => [
                    "message" => "string",
                ],
            ],
        ];
    }
}