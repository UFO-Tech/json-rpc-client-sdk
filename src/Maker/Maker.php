<?php

namespace Ufo\RpcSdk\Maker;

use Exception;
use Psr\Cache\InvalidArgumentException;
use ReflectionClass;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;
use Symfony\Bundle\MakerBundle\Util\ComposerAutoloaderFinder;
use Symfony\Bundle\MakerBundle\Util\MakerFileLinkFormatter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;
use Ufo\RpcError\RpcDataNotFoundException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\Helpers\TypeHintResolver;
use Ufo\RpcObject\RpcTransport;
use Ufo\RpcSdk\Exceptions\SdkBuilderException;
use Ufo\RpcSdk\Exceptions\UnsupportedFormatDocumentationException;
use Ufo\RpcSdk\Maker\Definitions\ArgumentDefinition;
use Ufo\RpcSdk\Maker\Definitions\ClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\MethodDefinition;
use Ufo\RpcSdk\Maker\Definitions\MethodToClassnameConvertor;
use Ufo\RpcSdk\Maker\Definitions\UfoEnvelope;
use Ufo\RpcSdk\Procedures\AsyncTransport;

use function count;
use function explode;
use function in_array;
use function preg_match;
use function str_replace;

class Maker
{
    const DEFAULT_NAMESPACE = 'Ufo\RpcSdk\Client';
    const DEFAULT_CACHE_LIFETIME = 3600;

    protected Generator $generator;
    protected array $rpcResponse;

    protected array $dtoStack = [];

    protected ?UfoEnvelope $envelope = null;

    /**
     * @var ClassDefinition[]
     */
    protected array $rpcProcedureClasses;

    public function __construct(
        protected string  $apiUrl,
        protected ?string $apiVendorAlias = null,
        protected array   $headers = [],
        protected string  $namespace = self::DEFAULT_NAMESPACE,
        protected ?string $projectRootDir = null,
        protected int     $cacheLifeTimeSecond = self::DEFAULT_CACHE_LIFETIME,
        protected ?CacheInterface $cache = null
    )
    {
        $this->projectRootDir = $projectRootDir ?? getcwd();
        $domain = parse_url(trim($this->apiUrl))["host"];
        if (empty($apiVendorAlias)) {
            $apiVendorAlias = str_replace('.', '', $domain);
        }
        $this->apiVendorAlias = Str::asCamelCase($apiVendorAlias);
        $this->init();
    }

    /**
     * @throws InvalidArgumentException
     * @throws CacheException
     */
    protected function init(): void
    {
        if (is_null($this->cache)) {
            $this->cache = new FilesystemAdapter(
                'ufo_sdk_maker_cache', // Унікальний префікс для ідентифікації вашого кешу
                $this->cacheLifeTimeSecond,
                $this->projectRootDir . '/var/cache/maker/'
            );
        }
        $this->getApiRpcDoc();
        $this->generator = new Generator(
            new FileManager(
                new Filesystem(),
                new AutoloaderUtil(
                    new ComposerAutoloaderFinder($this->namespace)
                ),
                new MakerFileLinkFormatter(),
                $this->projectRootDir
            ),
            $this->namespace
        );
    }

    /**
     * @return Generator
     */
    public function getGenerator(): Generator
    {
        return $this->generator;
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     * @throws CacheException
     */
    protected function getApiRpcDoc(): void
    {

        $apiUrl = $this->apiUrl;
        $headers = [
            'headers' => $this->headers,
        ];
        $cacheLifetime = $this->cacheLifeTimeSecond;
        $this->rpcResponse = $this->cache->get(
            'rpc.response' . $this->apiVendorAlias,
            function (ItemInterface $item)
            use ($apiUrl, $cacheLifetime, $headers): array {
                $item->expiresAfter($cacheLifetime);
                $client = HttpClient::create();
                $request = $client->request('GET', $apiUrl, $headers);
                return json_decode($request->getContent(), true);
            }
        );
        $this->supportVersions();
    }

    public function getRpcProcedures(): array
    {
        return $this->rpcResponse['methods'] ?? $this->rpcResponse['services'] ?? $this->rpcResponse['procedures'];

    }

    /**
     * @throws WrongWayException
     */
    public function getDtoSchema(string $name): array
    {
        $components = $this->rpcResponse['components'] ?? [];
        $schemas = $components['schemas'] ?? [];
        return $schemas[$name] ?? throw new WrongWayException();
    }

    protected function supportVersions(): void
    {
        if (!isset($this->rpcResponse['openrpc'])
            || !in_array($this->rpcResponse['openrpc'], UnsupportedFormatDocumentationException::SUPPORTED)) {
            throw new UnsupportedFormatDocumentationException();
        }

        try {
            $env = $this->rpcResponse['servers'][0]['x-ufo']['envelop'] ?? '';
            $matches = [];
            if (preg_match('/UFO-RPC-(\d)/', $env, $matches)) {
                $this->envelope = new UfoEnvelope((int)$matches[1]);
            }
        } catch (\Throwable) {}
    }

    /**
     * @param callable|null $callbackOutput
     * @return void
     * @throws Exception
     */
    public function make(?callable $callbackOutput = null): void
    {
        foreach ($this->getRpcProcedures() as $procedureData) {
            $procedureName = $procedureData['name'];
            if ($procedureName === 'ping') continue;

            try {
                $this->makeDto($procedureData);
            } catch (RpcDataNotFoundException) {}

            $this->classAddOrUpdate($procedureName, $procedureData);
            if (!empty($this->getRpcTransport(true))) {
                $this->classAddOrUpdate($procedureName, $procedureData, true);
            }
        }
        foreach ($this->rpcProcedureClasses as $rpcProcedureClass) {
            $this->removePreviousClass($rpcProcedureClass->getFullName());
            $creator = new SdkClassProcedureMaker($this, $rpcProcedureClass);
            $creator->generate();
            if (!is_null($callbackOutput)) {
                $callbackOutput($rpcProcedureClass);
            }
        }
        $schemas = $this->rpcResponse['components'] ?? [];
        $schemas = $schemas['schemas'] ?? [];
        if (count($this->dtoStack) < count($schemas)) {
            foreach ($schemas as $dtoName => $schema) {
                if (isset($this->dtoStack[$dtoName])) continue;
                $this->generateDto($dtoName);
            }
        }
    }

    protected function makeDto(array &$procedureData): void
    {
        $collection = false;
        try {
            $ref = DocHelper::getPath($procedureData['result']['schema'], '$ref');
        } catch (RpcDataNotFoundException) {
            $ref = DocHelper::getPath($procedureData['result']['schema'], 'items.$ref');
            $collection = true;
        }
        $p = explode('/', $ref);
        $dtoName = end($p);

        $dto = $this->generateDto($dtoName);

        if (!$collection) {
            MethodDefinition::addTypeExclude('DTO\\' . $dto);
            $procedureData['returns'] = 'DTO\\' . $dto;
        } else {
            $procedureData['returns'] = 'array';
            $procedureData['returnsDoc'] = 'DTO\\' . $dto . '[]';
        }
    }

    protected function generateDto(string $dtoName): string
    {
        $className = $this->dtoStack[$dtoName] ?? null;

        if (is_null($className)) {
            $className = ClassDefinition::toUpperCamelCase($dtoName);
            $this->dtoStack[$dtoName] = $className;

            $class = new DtoClassDefinition(
                $this->namespace . '\\' . $this->apiVendorAlias . '\\DTO',
                $className
            );
            $this->removePreviousClass($class->getFullName());
            $dtoSchema = $this->getDtoSchema($dtoName);
            $class->setProperties($dtoSchema['properties'] ?? []);
            $creator = new SdkClassDtoMaker($this, $class);
            $creator->generate();
        }
        return $className;
    }

    /**
     * @param string $className
     * @return void
     * @throws SdkBuilderException
     */
    protected function removePreviousClass(string $className): void
    {
        if (class_exists($className)) {
            try {
                $reflection = new ReflectionClass($className);
                unlink($reflection->getFileName());
                usleep(300);
            } catch (Throwable $e) {
                throw new SdkBuilderException(
                    'Can`t remove previous version for class "' . $className . '"',
                    previous: $e
                );
            }
        }
    }

    /**
     * @param string $procedureName
     * @param array $procedureData
     * @param bool $async
     * @return ClassDefinition
     */
    protected function classAddOrUpdate(string $procedureName, array $procedureData, bool $async = false):
    ClassDefinition
    {
        $ns = $this->namespace;
        $ns .= '\\' . $this->apiVendorAlias;

        $convertor = MethodToClassnameConvertor::convert($procedureName, $async);
        $method = new MethodDefinition($convertor->apiMethod, $procedureName);
        $returns = $procedureData['returns'] ?? DocHelper::getPath($procedureData, 'result.schema.type');
        $method->setReturns($returns, $procedureData['returnsDoc'] ?? null);
        try {
            $class = $this->getClassByName($convertor->className);
        } catch (SdkBuilderException) {
            $class = new ClassDefinition($ns, $convertor->className, $async);
            $this->rpcProcedureClasses[$convertor->className] = $class;
        }
        $class->addMethod($method);

        foreach ($procedureData['params'] as $data) {
            $assertions = $data['x-ufo-assertions'] ?? null;
            $type = 'mixed';
            $default = null;
            try {
                $type = TypeHintResolver::jsonSchemaToPhp($data['schema'] ?? DocHelper::getPath($data, 'schema'));
            } catch (\Throwable $e) {}
            try {
                $default = DocHelper::getPath($data, 'schema.default');
            } catch (RpcDataNotFoundException) {}

            $argument = new ArgumentDefinition(
                $data['name'],
                $type,
                !$data['required'],
                $default,
                $assertions
            );
            $method->addArgument($argument);
        }
        return $class;
    }

    /**
     * @param string $name
     * @return ClassDefinition
     * @throws SdkBuilderException
     */
    protected function getClassByName(string $name): ClassDefinition
    {
        if (!isset($this->rpcProcedureClasses[$name])) {
            throw new SdkBuilderException('Class definition "' . $name . '" not found');
        }
        return $this->rpcProcedureClasses[$name];
    }

    /**
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * @return string|null
     */
    public function getApiVendorAlias(): ?string
    {
        return $this->apiVendorAlias;
    }

    public function getRpcTransport(bool $async = false): string
    {
        $type = $async ? 'async' : 'sync';
        try {
            return str_replace(
                '{user}:{pass}',
                AsyncTransport::PLACEHOLDER,
                (string)RpcTransport::fromArray($this->rpcResponse['transport'][$type] ?? [])
            );
        } catch (\Throwable) {
            return '';
        }
    }


}
