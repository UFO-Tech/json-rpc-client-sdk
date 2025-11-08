<?php

namespace Ufo\RpcSdk\Maker\Definitions\Configs;

use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcObject\RpcTransport;
use Ufo\RpcSdk\Exceptions\UnsupportedFormatDocumentationException;
use Ufo\RpcSdk\Maker\Definitions\UfoEnvelope;
use Ufo\RpcSdk\Maker\DocReader\Interfaces\IDocReader;
use Ufo\RpcSdk\Maker\Helpers\DocHelper;
use Ufo\RpcSdk\Procedures\AsyncTransport;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function current;
use function in_array;
use function is_null;
use function preg_match;
use function str_replace;

class ConfigsHolder
{
    const string DEFAULT_NAMESPACE = 'Ufo\RpcSdk\Client';
    const int DEFAULT_CACHE_LIFETIME = 3600;

    readonly protected ?UfoEnvelope $envelope;

    readonly public array $rpcResponse;
    readonly public array $rpcSchema;

    /**
     * @var array<string,ProcedureConfig>
     */
    protected array $rpcProcedures = [];

    /**
     * @var array<string,DtoConfig>
     */
    protected array $dtos = [];

    /**
     * @var array <string,string>
     */
    protected array $defaultValues = [];

    readonly public string $apiUrl;

    readonly public string $apiVendorAlias;

    /**
     * @throws UnsupportedFormatDocumentationException
     * @throws InvalidArgumentException
     * @throws CacheException
     */
    public function __construct(
        protected IDocReader $docReader,
        readonly public string $projectRootDir,
        string $apiVendorAlias,
        readonly public string $namespace = self::DEFAULT_NAMESPACE,
        readonly public bool $urlInAttr = true,
        readonly protected int $cacheLifeTimeSecond = self::DEFAULT_CACHE_LIFETIME,
        protected ?CacheInterface $cache = null,
    )
    {
        $this->apiVendorAlias = Str::asCamelCase($apiVendorAlias);
        $this->init();
    }

    /**
     * @return void
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws UnsupportedFormatDocumentationException
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
        $allSchema = $this->getApiRpcDoc();

        $components = $allSchema['components'] ?? [];
        $allSchema['components'] = [];
        $dtoSchemas = array_filter(
            $components['schemas'] ?? [],
            fn($schema) => $schema[TypeHintResolver::TYPE] === TypeHintResolver::OBJECT->value
        );
        foreach ($dtoSchemas as $dtoName => $dto) {
            $dtoName = Str::asClassName($dtoName);
            $dto['properties'] = array_combine(
                array_keys($dto['properties'] ?? []),
                array_map(
                    fn(array $schema) => DocHelper::getComponentData($schema, $components),
                    $dto['properties'] ?? []
                )
            );
            $allSchema['components']['schemas'][$dtoName] = $dto;
            $properties = array_map(
                fn(string $name, array $schema) => [
                    'name' => $name,
                    'schema' => $schema,
                    'required' => in_array($name, $dto['required'] ?? []) || !array_key_exists('default', $schema),
                ],
                array_keys($dto['properties'] ?? []),
                $dto['properties'] ?? []
            );
            $this->dtos[$dtoName] = DtoConfig::fromArray($dtoName, $properties);
        }

        $procedures = $allSchema['methods'] ?? $allSchema['services'] ?? $allSchema['procedures'];
        foreach ($procedures as $procedure) {
            if ($procedure['name'] === 'ping') continue;
            foreach ($procedure['params'] ?? [] as $i => $param) {
                $procedure['params'][$i] = DocHelper::getComponentData($param, $components);
            }
            $procedure['result'] = DocHelper::getComponentData($procedure['result'] ?? [], $components);

            $this->rpcProcedures[$procedure['name']] = ProcedureConfig::fromArray($procedure);
        }

        $this->rpcSchema = $allSchema;
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     * @throws CacheException|UnsupportedFormatDocumentationException
     */
    protected function getApiRpcDoc(): array
    {

        $cacheLifetime = $this->cacheLifeTimeSecond;
        $this->rpcResponse = $this->cache->get(
            'rpc.response' . $this->apiVendorAlias,
            function (ItemInterface $item) use ($cacheLifetime): array {
                $item->expiresAfter($cacheLifetime);
                return $this->docReader->getApiDocumentation();
            }
        );
        $this->supportVersions();
        return $this->rpcResponse;
    }

    protected function supportVersions(): void
    {
        if (!isset($this->rpcResponse['openrpc'])
            || !in_array($this->rpcResponse['openrpc'], UnsupportedFormatDocumentationException::SUPPORTED)) {
            throw new UnsupportedFormatDocumentationException();
        }

        try {
            $server = $this->rpcResponse['servers'][0] ?? [];
            $env = $server[EnumResolver::CORE]['envelop'] ?? '';
            $matches = [];
            if (preg_match('/UFO-RPC-(\d)/', $env, $matches)) {
                $this->envelope = new UfoEnvelope((int)$matches[1]);
            }
        } catch (Throwable) {}
        $this->apiUrl = $server['url'] ?? throw new UnsupportedFormatDocumentationException('No url config in server block');
    }

    /**
     * @return array<string,ProcedureConfig>
     */
    public function getRpcProcedures(): array
    {
        return $this->rpcProcedures;
    }

    /**
     * @return array<string,DtoConfig>
     */
    public function getDtos(): array
    {
        return $this->dtos;
    }

    public function getRpcTransport(bool $async = false): string
    {
        $type = $async ? 'async' : 'sync';
        try {
            return str_replace(
                '{user}:{pass}',
                AsyncTransport::PLACEHOLDER,
                (string)RpcTransport::fromArray(
                    current($this->rpcResponse['servers'])[EnumResolver::CORE]['transport'][$type] ?? []
                )
            );
        } catch (Throwable) {
            return '';
        }
    }

    public function addDefaultValueForParam(ParamConfig $paramConfig, mixed $defaultValue): void
    {
        $this->defaultValues[$paramConfig->parentConfig->name][$paramConfig->name] = $defaultValue;
    }

    public function getDefaultValueForParam(string $className, string $paramName): mixed
    {
        return $this->defaultValues[$className][$paramName] ?? null;
    }
}