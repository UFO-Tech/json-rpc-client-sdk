<?php

namespace Ufo\RpcSdk\Maker\Definitions\Configs;

use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\RpcObject\RpcTransport;
use Ufo\RpcSdk\Exceptions\UnsupportedFormatDocumentationException;
use Ufo\RpcSdk\Maker\Definitions\UfoEnvelope;
use Ufo\RpcSdk\Maker\DocReader\Interfaces\IDocReader;
use Ufo\RpcSdk\Procedures\AsyncTransport;

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

    /**
     * @var array<string,ProcedureConfig>
     */
    protected array $rpcProcedures = [];

    /**
     * @var array<string,DtoConfig>
     */
    protected array $dtos = [];

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
        $this->getApiRpcDoc();

        $procedures = $this->rpcResponse['methods'] ?? $this->rpcResponse['services'] ?? $this->rpcResponse['procedures'];
        foreach ($procedures as $procedure) {
            if ($procedure['name'] === 'ping') continue;
            $this->rpcProcedures[$procedure['name']] = ProcedureConfig::fromArray($procedure);
        }
        foreach ($this->rpcResponse['components']['schemas'] ?? [] as $dtoName => $dto) {
            $properties = array_map(
                fn(string $name, array $schema) => [
                    'name' => $name,
                    'schema' => $schema,
                    'required' => in_array($name, $dto['required']),
                ],
                array_keys($dto['properties'] ?? []),
                $dto['properties'] ?? []
            );
            $this->dtos[$dtoName] = DtoConfig::fromArray($dtoName, $properties);
        }
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     * @throws CacheException|UnsupportedFormatDocumentationException
     */
    protected function getApiRpcDoc(): void
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
        } catch (\Throwable) {}
        $this->apiUrl = $server['url'] ?? throw new UnsupportedFormatDocumentationException('No url config in server block');;

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
        } catch (\Throwable) {
            return '';
        }
    }
}