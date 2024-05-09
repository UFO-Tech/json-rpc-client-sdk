<?php

namespace Ufo\RpcSdk\Maker;

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
use Ufo\RpcSdk\Exceptions\SdkBuilderException;
use Ufo\RpcSdk\Maker\Definitions\ArgumentDefinition;
use Ufo\RpcSdk\Maker\Definitions\ClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\MethodDefinition;
use Ufo\RpcSdk\Maker\Definitions\UfoEnvelope;

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
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Component\Cache\Exception\CacheException
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
        $this->checkUfoEnvelop();
    }

    public function getRpcProcedures(): array
    {
        return $this->rpcResponse['services'] ?? $this->rpcResponse['procedures'];

    }

    protected function checkUfoEnvelop(): void
    {
        $env = $this->rpcResponse['envelope'] ?? '';
        $matches = [];
        if (preg_match('/UFO-RPC-(\d)/', $env, $matches)) {
            $this->envelope = new UfoEnvelope((int)$matches[1]);
        }
    }

    /**
     * @param callable|null $callbackOutput
     * @return void
     * @throws \Exception
     */
    public function make(?callable $callbackOutput = null): void
    {
        foreach ($this->getRpcProcedures() as $procedureName => $procedureData) {
            $this->makeDto($procedureData);
            $this->classAddOrUpdate($procedureName, $procedureData);
        }
        foreach ($this->rpcProcedureClasses as $rpcProcedureClass) {
            $this->removePreviousClass($rpcProcedureClass->getFullName());
            $creator = new SdkClassProcedureMaker($this, $rpcProcedureClass);
            $creator->generate();
            if (!is_null($callbackOutput)) {
                $callbackOutput($rpcProcedureClass);
            }
        }
    }

    protected function makeDto(array &$procedureData): void
    {
        if ($this->envelope && !empty($procedureData['responseFormat'])) {
            $dto = $this->generateDto($procedureData);

            if ($procedureData['returns'] === 'object'
                || ($procedureData['returns'] === 'array' && $procedureData['is_collection'] === false)
            ) {
                MethodDefinition::addTypeExclude('DTO\\' . $dto);
                $procedureData['returns'] = 'DTO\\' . $dto;
            } else {
                $procedureData['returnsDoc'] = 'DTO\\' . $dto . '[]';
            }
        }
    }

    protected function generateDto(array &$procedureData): string
    {
        $procedureData['is_collection'] = false;
        $format = $procedureData['responseFormat'];
        if (isset($format[0])) {
            $format = $format[0];
            $procedureData['is_collection'] = true;
        }
        $className = $this->dtoStack[md5(serialize($format))] ?? null;

        if (is_null($className)) {
            $className = SdkClassDtoMaker::generateName($procedureData['name']);
            $this->dtoStack[md5(serialize($format))] = $className;

            $class = new ClassDefinition(
                $this->namespace . '\\' . $this->apiVendorAlias . '\\DTO',
                $className
            );
            $this->removePreviousClass($class->getFullName());
            $class->setProperties($procedureData['responseFormat']);
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
            } catch (\Throwable $e) {
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
     * @return ClassDefinition
     */
    protected function classAddOrUpdate(string $procedureName, array $procedureData): ClassDefinition
    {
        $ns = $this->namespace;
        $ns .= '\\' . $this->apiVendorAlias;

        $pArray = explode('.', $procedureName);
        if (count($pArray) == 1) {
            $className = 'Main';
        } else {
            $className = Str::asCamelCase($pArray[0]);
        }
        $method = new MethodDefinition(end($pArray), $procedureName);
        $method->setReturns($procedureData['returns'], $procedureData['returnsDoc'] ?? null);
        try {
            $class = $this->getClassByName($className);
        } catch (SdkBuilderException) {
            $class = new ClassDefinition($ns, $className);
            $this->rpcProcedureClasses[$className] = $class;
        }
        $class->addMethod($method);

        foreach ($procedureData['parameters'] as $data) {
            $argument = new ArgumentDefinition(
                $data['name'],
                $data['type'],
                $data['optional'],
                $data['default'] ?? null,
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
}
