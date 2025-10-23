<?php

namespace Ufo\RpcSdk\Tests\Procedures\ResponseTransformer;

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Ufo\DTO\DTOTransformer;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcSdk\Procedures\ApiMethod;
use Ufo\RpcSdk\Procedures\CallApiDefinition;
use Ufo\RpcSdk\Procedures\ResponseTransformer\CollectionResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\DtoResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Exceptions\SdkResponseHandlerException;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Exceptions\SdkResultUnionTypeIsBrokedException;
use Ufo\RpcSdk\Procedures\ResponseTransformer\UnionResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\EnumResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\SdkResponseCreator;
use Ufo\RpcSdk\Tests\Fixtures\DTO\DummyDTO;
use Ufo\RpcSdk\Tests\Fixtures\DTO\TestSmartUserDTO;
use Ufo\RpcSdk\Tests\Fixtures\DTO\TestUserDTO;
use Ufo\RpcSdk\Tests\Fixtures\Enums\StringEnum;

use function json_encode;

class SdkResponseCreatorTest extends TestCase
{
    public function testFromSimpleApiResponseReturnsRpcResponseObject(): void
    {
        $json = $this->createResponseJson(["key" => "value"]);
        $apiDefinition = $this->createApiDefinition('tesSimple', 'array<string,string>');
        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);
    }

    public function testFromDTOApiResponseReturnsRpcResponseObject(): void
    {
        $json = $this->createResponseJson([
            "name" => "Ivan",
            "status" => 1,
            "email" => "test@test",
        ]);
        $apiDefinition = $this->createApiDefinition('testDTO', TestUserDTO::class);
        $response = SdkResponseCreator::fromApiResponse(
            $json,
            $apiDefinition,
            $this->getHandlers()
        );
        $this->assertRpcResponse($response);
        $result = $response->getResult(true);
        $this->assertInstanceOf(TestUserDTO::class, $result);
        $this->assertSame(1, $result->status);
        $this->assertSame('Ivan', $result->name);
        $this->assertSame('test@test', $result->email);
    }

    public function testFromDTOApiResponseReturnsRpcResponseObjectWithSmartArrayData(): void
    {
        $json = $this->createResponseJson([
            "name" => "Ivan",
            "status" => 1,
            "email" => "test@test",
            DTOTransformer::DTO_CLASSNAME => 'TestSmartUserDTO'
        ]);
        $apiDefinition = $this->createApiDefinition('testSmartDTO', TestUserDTO::class);
        $response = SdkResponseCreator::fromApiResponse(
            $json,
            $apiDefinition,
            $this->getHandlers()
        );
        $this->assertRpcResponse($response);
        $result = $response->getResult(true);
        $this->assertInstanceOf(TestSmartUserDTO::class, $result);
        $this->assertSame(1, $result->status);
        $this->assertSame('Ivan', $result->name);
        $this->assertSame('test@test', $result->email);
    }


    public function testFromCollectionDTOApiResponseReturnsRpcResponseObject(): void
    {
        $json = $this->createResponseJson([
            [
                "name" => "Ivan",
                "status" => 1,
                "email" => "test@test",
            ],
            [
                "name" => "Peter",
                "status" => 10,
                "email" => "test2@test",
            ],
        ]);
        $apiDefinition = $this->createApiDefinition('testCollectionDTO', TestUserDTO::class . '[]');
        $response = SdkResponseCreator::fromApiResponse(
            $json,
            $apiDefinition,
            $this->getHandlers()
        );
        $this->assertRpcResponse($response);
        $result = $response->getResult(true);

        $this->assertIsArray($result);
        $this->assertInstanceOf(TestUserDTO::class, $result[0]);
        $this->assertInstanceOf(TestUserDTO::class, $result[1]);

        $this->assertSame(1, $result[0]->status);
        $this->assertSame('Ivan', $result[0]->name);
        $this->assertSame('test@test', $result[0]->email);

        $this->assertSame(10, $result[1]->status);
        $this->assertSame('Peter', $result[1]->name);
        $this->assertSame('test2@test', $result[1]->email);
    }

    public function testFromCollectionDTOApiResponseReturnsRpcResponseObjectNegative(): void
    {
        $this->expectException(SdkResponseHandlerException::class);
        $json = $this->createResponseJson([
            [
                "phone" => "380950783812",
            ],
            [
                "name" => "Ivan",
                "status" => 1,
                "email" => "test@test",
            ],
            [
                "name" => "Peter",
                "status" => 10,
                "email" => "test2@test",
            ],
        ]);
        $apiDefinition = $this->createApiDefinition('testCollectionNegativeDTO', DummyDTO::class . '[]');
        $response = SdkResponseCreator::fromApiResponse(
            $json,
            $apiDefinition,
            $this->getHandlers()
        );
        $this->assertRpcResponse($response);
    }

    public function testArrayOfStringToUserDto(): void
    {
        $json = $this->createResponseJson([
            'first' => ['name' => 'Ivan', 'status' => 1, 'email' => 'a@test'],
            'second' => ['name' => 'Oleh', 'status' => 2, 'email' => 'b@test'],
        ]);
        $apiDefinition = $this->createApiDefinition('testArrayUser', 'array<string,' . TestUserDTO::class . '>');

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);

        $result = $response->getResult(true);
        $this->assertArrayHasKey('first', $result);
        $this->assertInstanceOf(TestUserDTO::class, $result['first']);
        $this->assertSame('Ivan', $result['first']->name);

        $this->assertArrayHasKey('second', $result);
        $this->assertInstanceOf(TestUserDTO::class, $result['second']);
        $this->assertSame('Oleh', $result['second']->name);
    }

    public function testArrayOfUnionUserOrDummyDTO(): void
    {
        $json = $this->createResponseJson([
            ['name' => 'Ivan', 'status' => 1, 'email' => 'a@test'],
            ['phone' => '38099999999'],
        ]);
        $apiDefinition = $this->createApiDefinition('testUnionArray', 'array<' . TestUserDTO::class . '|' . DummyDTO::class . '>');

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);

        $data = $response->getResult(true);
        $this->assertInstanceOf(TestUserDTO::class, $data[0]);
        $this->assertInstanceOf(DummyDTO::class, $data[1]);
    }
    public function testUnionOfArrayUserOrDummyPositive(): void
    {
        $userJson = $this->createResponseJson([
            ['name' => 'Ivan', 'status' => 1, 'email' => 'a@test'],
            ['name' => 'Oleh', 'status' => 2, 'email' => 'b@test'],
        ]);

        $dummyJson = $this->createResponseJson([
            ['phone' => '38099999999'],
            ['phone' => '38088888888'],
        ]);

        $apiDefinition = $this->createApiDefinition('testUnionArrayType', TestUserDTO::class . '[]|' . DummyDTO::class . '[]');

        $userResult = SdkResponseCreator::fromApiResponse($userJson, $apiDefinition, $this->getHandlers());
        $dummyResult = SdkResponseCreator::fromApiResponse($dummyJson, $apiDefinition, $this->getHandlers());


        $this->assertRpcResponse($userResult);
        $this->assertRpcResponse($dummyResult);

        $userData = $userResult->getResult(true);
        $dummyData = $dummyResult->getResult(true);

        $this->assertInstanceOf(TestUserDTO::class, $userData[0]);
        $this->assertInstanceOf(TestUserDTO::class, $userData[1]);

        $this->assertInstanceOf(DummyDTO::class, $dummyData[0]);
        $this->assertInstanceOf(DummyDTO::class, $dummyData[1]);
    }

    public function testUnionOfArrayUserOrDummyNegative(): void
    {
        $this->expectException(SdkResultUnionTypeIsBrokedException::class);

        $userJson = $this->createResponseJson([
            ['name' => 'Ivan', 'status' => 1, 'email' => 'a@test'],
            ['name' => 'Oleh', 'status' => 2, 'email' => 'b@test'],
            ['phone' => '38099999999'],
            ['phone' => '38088888888'],
        ]);

        $apiDefinition = $this->createApiDefinition('testUnionArrayType', TestUserDTO::class . '[]|' . DummyDTO::class . '[]');

        $response = SdkResponseCreator::fromApiResponse($userJson, $apiDefinition, $this->getHandlers());

        $this->assertRpcResponse($response);
    }

    public function testUnionOfSingleUserOrDummy(): void
    {
        $json = $this->createResponseJson(['name' => 'Ivan', 'status' => 1, 'email' => 'x@test']);
        $apiDefinition = $this->createApiDefinition('testUnionSingle', TestUserDTO::class . '|' . DummyDTO::class);

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);

        $dto = $response->getResult(true);
        $this->assertInstanceOf(TestUserDTO::class, $dto);
    }

    public function testNullableUserDTO(): void
    {
        $json = $this->createResponseJson(null);
        $apiDefinition = $this->createApiDefinition('testNullable', '?' . TestUserDTO::class);

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);

        $this->assertNull($response->getResult(true));
    }

    public function testNestedArrayOfUnion(): void
    {
        $json = $this->createResponseJson([
            'a' => [
                ['name' => 'Ivan', 'status' => 1, 'email' => 'x@test'],
                ['phone' => '38099999999'],
            ],
            'b' => [
                ['name' => 'Oleh', 'status' => 2, 'email' => 'y@test'],
            ],
        ]);

        $apiDefinition = $this->createApiDefinition('testNestedUnion', 'array<string, array<' . TestUserDTO::class . '|' . DummyDTO::class . '>>');

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);

        $data = $response->getResult(true);
        $this->assertInstanceOf(TestUserDTO::class, $data['a'][0]);
        $this->assertInstanceOf(DummyDTO::class, $data['a'][1]);
        $this->assertInstanceOf(TestUserDTO::class, $data['b'][0]);
    }

    public function testArrayOfUnionArrays(): void
    {
        $json = $this->createResponseJson([
            'a' => [
                ['name' => 'Ivan', 'status' => 1, 'email' => 'x@test'],
            ],
            'b' => [
                ['phone' => '38099999999'],
            ],
        ]);

        $apiDefinition = $this->createApiDefinition('testUnionArrayOfArrays', 'array<string,' . TestUserDTO::class . '[]|' . DummyDTO::class . '[]>');

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);

        $data = $response->getResult(true);
        $this->assertInstanceOf(TestUserDTO::class, $data['a'][0]);
        $this->assertInstanceOf(DummyDTO::class, $data['b'][0]);
    }

    public function testArrayOfStringEnum(): void
    {
        $json = $this->createResponseJson(['a', 'b']);
        $apiDefinition = $this->createApiDefinition(
            'testArrayEnum',
            'array<' . StringEnum::class . '>',
            'Ufo\\RpcSdk\\Tests\\Fixtures'
        );

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);

        $data = $response->getResult(true);
        $this->assertCount(2, $data);
        $this->assertInstanceOf(StringEnum::class, $data[0]);
        $this->assertSame('a', $data[0]->value);
        $this->assertSame('b', $data[1]->value);
    }

    public function testSingleStringEnumNegative(): void
    {
        $this->expectException(SdkResponseHandlerException::class);
        $json = $this->createResponseJson('c');
        $apiDefinition = $this->createApiDefinition(
            'testSingleEnumNegative',
            StringEnum::class,
            'Ufo\\RpcSdk\\Tests\\Fixtures'
        );

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);
    }

    public function testSingleStringEnum(): void
    {
        $json = $this->createResponseJson('a');
        $apiDefinition = $this->createApiDefinition(
            'testSingleEnum',
            StringEnum::class
        );

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);

        $enum = $response->getResult(true);
        $this->assertInstanceOf(StringEnum::class, $enum);
        $this->assertSame('a', $enum->value);
    }

    public function testUnionIntOrString(): void
    {
        $jsonInt = $this->createResponseJson(123);
        $jsonString = $this->createResponseJson('hello');
        $apiDefinition = $this->createApiDefinition('testIntOrString', 'int|string');

        $intResult = SdkResponseCreator::fromApiResponse($jsonInt, $apiDefinition, $this->getHandlers());
        $stringResult = SdkResponseCreator::fromApiResponse($jsonString, $apiDefinition, $this->getHandlers());

        $this->assertRpcResponse($intResult);
        $this->assertRpcResponse($stringResult);

        $this->assertSame(123, $intResult->getResult(true));
        $this->assertSame('hello', $stringResult->getResult(true));
    }

    public function testSimpleString(): void
    {
        $json = $this->createResponseJson('simple text');
        $apiDefinition = $this->createApiDefinition('testString', 'string');

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);
        $result = $response->getResult(true);

        $this->assertSame('simple text', $result);
    }

    public function testSimpleBool(): void
    {
        $json = $this->createResponseJson(true);
        $apiDefinition = $this->createApiDefinition('testBool', 'bool');

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);

        $this->assertTrue($response->getResult(true));
    }

    public function testArrayStringInt(): void
    {
        $json = $this->createResponseJson(['a' => 1, 'b' => 2, 'c' => 3]);
        $apiDefinition = $this->createApiDefinition('testArrayStringInt', 'array<string,int>');

        $response = SdkResponseCreator::fromApiResponse($json, $apiDefinition, $this->getHandlers());
        $this->assertRpcResponse($response);

        $data = $response->getResult(true);
        $this->assertIsArray($data);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $data);
    }



    //==================================================

    /**
     * @param string $method
     * @param string $returnType
     * @return CallApiDefinition
     * @throws Exception
     */
    private function createApiDefinition(string $method, string $returnType, ?string $ns = null): CallApiDefinition
    {
        $apiMethod = new ApiMethod($method);
        $refMethod = $this->createMock(ReflectionMethod::class);
        $refMethod->method('getDocComment')->willReturn("/** @return $returnType */");
        $refClass = $this->createMock(ReflectionClass::class);
        $refClass->method('getNamespaceName')->willReturn($ns ?? 'Ufo\\RpcSdk\\Tests\\Fixtures');
        return new CallApiDefinition($refClass, $refMethod, $apiMethod, []);
    }

    /**
     * @param RpcResponse $response
     */
    private function assertRpcResponse(RpcResponse $response): void
    {
        $this->assertInstanceOf(RpcResponse::class, $response);
        $this->assertSame(1, $response->getId());
        $this->assertSame('2.0', $response->getVersion());
        $this->assertNull($response->getError());
    }

    private function getHandlers(): iterable
    {
        return [
            new EnumResponseHandler(),
            new DtoResponseHandler(),
            new CollectionResponseHandler(),
            new UnionResponseHandler(),
        ];
    }

    private function createResponseJson(mixed $return): string
    {
        return json_encode([
            'id' => 1,
            'result' => $return,
            'jsonrpc' => '2.0',
        ]);
    }
}