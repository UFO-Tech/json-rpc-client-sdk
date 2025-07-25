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
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcObject\DocsHelper\XUfoValuesEnum;
use Ufo\RpcObject\RpcTransport;
use Ufo\RpcSdk\Exceptions\SdkBuilderException;
use Ufo\RpcSdk\Exceptions\UnsupportedFormatDocumentationException;
use Ufo\RpcSdk\Maker\Definitions\ArgumentDefinition;
use Ufo\RpcSdk\Maker\Definitions\ClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\EnumDefinition;
use Ufo\RpcSdk\Maker\Definitions\MethodDefinition;
use Ufo\RpcSdk\Maker\Definitions\MethodToClassnameConvertor;
use Ufo\RpcSdk\Maker\Definitions\UfoEnvelope;
use Ufo\RpcSdk\Procedures\AsyncTransport;

use function count;
use function current;
use function explode;
use function file_get_contents;
use function implode;
use function in_array;
use function json_decode;
use function md5;
use function preg_match;
use function preg_replace;
use function str_contains;
use function str_replace;
use function ucfirst;

class Maker
{
    const string DEFAULT_NAMESPACE = 'Ufo\RpcSdk\Client';
    const int DEFAULT_CACHE_LIFETIME = 3600;

    protected Generator $generator;
    protected array $rpcResponse;

    protected array $dtoStack = [];

    /**
     * @var EnumDefinition[]
     */
    protected array $enumStack = [];

    protected ?UfoEnvelope $envelope = null;

    /**
     * @var ClassDefinition[]
     */
    protected array $rpcProcedureClasses;

    public function __construct(
        protected string  $apiUrl,
        protected ?string $apiVendorAlias = null,
        protected array   $headers = [],
        readonly public string  $namespace = self::DEFAULT_NAMESPACE,
        protected ?string $projectRootDir = null,
        protected int     $cacheLifeTimeSecond = self::DEFAULT_CACHE_LIFETIME,
        protected ?CacheInterface $cache = null,
        protected bool $urlInAttr = true
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

    public function getProjectRootDir(): ?string
    {
        return $this->projectRootDir;
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
     * @throws CacheException|UnsupportedFormatDocumentationException
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
            $env = $this->rpcResponse['servers'][0][XUfoValuesEnum::CORE->value]['envelop'] ?? '';
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
            $creator = new SdkClassProcedureMaker($this, $rpcProcedureClass, $this->urlInAttr);
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
        try {
            $this->generateEnums();
        } catch (RpcDataNotFoundException) {}

        (new SdkConfigMaker($this))->generate();
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
        $dtoName = $this->getDtoNameFromRef($ref);
        $dto = $this->generateDto($dtoName);

        if (!$collection) {
            MethodDefinition::addTypeExclude(DtoClassDefinition::dtoWithNamespace($dto));
            $procedureData['returns'] = DtoClassDefinition::dtoWithNamespace($dto);
        } else {
            $procedureData['returns'] = 'array';
            $procedureData['returnsDoc'] = DtoClassDefinition::dtoWithNamespace($dto, true);
        }
    }

    /**
     * @throws Exception
     */
    protected function generateEnums(): void
    {
        foreach ($this->enumStack as $hash => $enumDefinition) {
            $this->removePreviousClass($enumDefinition->getFullName());
            $creator = new SdkEnumMaker($this, $enumDefinition);
            $creator->generate();
        }
    }

    protected function generateDto(string $dtoName): string
    {
        $className = $this->dtoStack[$dtoName] ?? null;

        if (is_null($className)) {
            $className = ClassDefinition::toUpperCamelCase($dtoName);
            $this->dtoStack[$dtoName] = $className;

            $class = new DtoClassDefinition(
                $this->namespace . '\\' . $this->apiVendorAlias . '\\' . DtoClassDefinition::FOLDER,
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
    protected function classAddOrUpdate(string $procedureName, array $procedureData, bool $async = false): ClassDefinition
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
            $assertions = $data[XUfoValuesEnum::ASSERTIONS->value] ?? null;
            $type = 'mixed';
            $default = null;
            try {
                $schema = $data['schema'] ?? DocHelper::getPath($data, 'schema');

                $type = TypeHintResolver::jsonSchemaToPhp($schema);
                $this->addEnums(
                    $type,
                    $schema,
                    $data,
                    $assertions
                );
            } catch (\Throwable) {}

            try {
                $default = DocHelper::getPath($data, 'schema.default');
            } catch (RpcDataNotFoundException) {}

            if ($type === TypeHintResolver::OBJECT->value && ($ref = $schema['$ref'] ?? false)) {
                $dtoName = $this->getDtoNameFromRef($ref);
                $type = DtoClassDefinition::dtoWithNamespace($this->generateDto($dtoName));;
            }

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

    protected function addEnums(
        string $type,
        array $schema,
        array $data,
        string &$assertions
    ): void
    {
        $enum = $schema['enum'] ?? $schema['items']['enum'] ?? null;
        if ($enum) {
            $hash = md5(implode(',', $enum));

            $enumDef = $this->enumStack[$hash] ?? null;

            if (!$enumDef) {
                $enumDef = new EnumDefinition(
                    $this->namespace . '\\' . $this->apiVendorAlias . '\\' . EnumDefinition::FOLDER,
                    $schema[XUfoValuesEnum::ENUM_NAME->value] ?? ucfirst($data['name']) . 'Enum',
                    str_contains($type, 'int') ? 'int' : 'string',
                    $schema[XUfoValuesEnum::ENUM->value] ?? $enum
                );
                $this->enumStack[$hash] = $enumDef;
            }
            $callbackEnumReg = '/callback: \[\w+::class,\s?[\'"]\w+[\'"]\]/';
            $assertions = preg_replace(
                $callbackEnumReg,
                'callback: [' . $enumDef->enumWithNamespace() . '::class, \'values\']',
                $assertions
            );
        }
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
                (string)RpcTransport::fromArray(
                    current($this->rpcResponse['servers'])[XUfoValuesEnum::CORE->value]['transport'][$type] ?? []
                )
            );
        } catch (\Throwable) {
            return '';
        }
    }

    protected function getDtoNameFromRef(mixed $ref): string
    {
        $p = explode('/', $ref);
        return end($p);
    }

}
