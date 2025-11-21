<?php

namespace Ufo\RpcSdk\Procedures;

use Symfony\Component\Yaml\Yaml;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcSdk\Exceptions\ConfigNotFoundException;

use function file_exists;
use function is_array;

class SdkConfigs
{
    const string CONFIG_NAME = 'sdk_config.yaml';
    const string DIST = '.dist';

    const string SYNC = 'sync';
    const string ASYNC = 'rpc_async';

    public function __construct(
        readonly public string $path,
    ) {}

    public function getConfigs(bool $dist = false): array
    {
        $path = $dist ? $this->getConfigDistPath() : $this->getConfigPath();
        try {
            if (!file_exists($path)) throw new WrongWayException();
            $configs = Yaml::parseFile($path);
        } catch (WrongWayException $e) {
            $configs = [];
        }
        return $configs;
    }

    public function getApiEndpoint(string $vendor, bool $sync = true): string
    {
        $config = $this->getConfigs()[$vendor] ?? $this->getConfigs(true)[$vendor] ?? throw new ConfigNotFoundException();
        if (is_array($config)) {
            $key = $sync ? static::SYNC : static::ASYNC;
            $config = $config[$key] ?? throw new ConfigNotFoundException();
        }
        return $config;
    }

    public function getConfigPath(): string
    {
        return $this->path . '/' . self::CONFIG_NAME;
    }

    public function getConfigDistPath(): string
    {
        return $this->getConfigPath() . self::DIST;
    }
}