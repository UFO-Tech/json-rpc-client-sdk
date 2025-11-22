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
use Ufo\RpcSdk\Exceptions\SdkBuilderException;
use Ufo\RpcSdk\Exceptions\UnsupportedFormatDocumentationException;
use Ufo\RpcSdk\Maker\Definitions\UfoEnvelope;
use Ufo\RpcSdk\Maker\DocReader\Interfaces\IDocReader;
use Ufo\RpcSdk\Maker\Helpers\DocHelper;
use Ufo\RpcSdk\Procedures\AsyncTransport;
use Ufo\RpcSdk\Procedures\SdkConfigs;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function current;
use function in_array;
use function is_null;
use function preg_match;
use function preg_quote;
use function str_replace;
use function substr;

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
        readonly public array $ignoredMethods = [],
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
        $type = $async ? SdkConfigs::ASYNC : SdkConfigs::SYNC;
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

    protected function maskToRegex(string $mask): string
    {
        $escaped = preg_quote($mask, '/');
        $regex = str_replace('\*', '.*', $escaped);
        return '/^' . $regex . '$/i';
    }

    protected function parseMask(string $mask): array
    {
        $inverted = $asyncOnly = $syncOnly = false;

        $prepare = function (string &$mask, string $symbol, bool &$flag): string {
            if (str_starts_with($mask, $symbol)) {
                $mask = substr($mask, 1);
                $flag = true;
            }
            return $mask;
        };

        $prepare($mask, '!', $inverted);
        $prepare($mask, '~', $asyncOnly);
        $prepare($mask, '&', $syncOnly);

        if ($syncOnly && $asyncOnly) throw new SdkBuilderException('Mask for method cannot contain both & and ~ symbols');

        return [$mask, $inverted, $syncOnly, $asyncOnly];
    }

    public function methodShouldIgnore(string $methodName, bool $async = false): bool
    {
        $ignore = false;

        foreach ($this->ignoredMethods as $mask) {
            [$mask, $inverted, $syncOnly, $asyncOnly] = $this->parseMask($mask);

            if (($syncOnly && $async) || ($asyncOnly && !$async)) continue;

            if (preg_match($this->maskToRegex($mask), $methodName)) {
                if ($inverted) {
                    $ignore = false;
                    break;
                }
                $ignore = true;
            }
        }

        return $ignore;
    }
}