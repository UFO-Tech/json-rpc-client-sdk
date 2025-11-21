<?php

namespace Ufo\RpcSdk\Maker;


use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\RpcObject\RpcTransport;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\Interfaces\IMaker;
use Ufo\RpcSdk\Procedures\AsyncTransport;
use Ufo\RpcSdk\Procedures\SdkConfigs;

use function current;
use function file_put_contents;
use function str_replace;

class SdkConfigMaker implements IMaker
{
    const string AUTOLOAD_PSR4 = '/vendor/composer/autoload_psr4.php';

    readonly public SdkConfigs $sdkConfigs;

    public function __construct(
        readonly public ConfigsHolder $configsHolder,
        protected Generator $generator,
    )
    {
        $this->sdkConfigs = new SdkConfigs($this->fillConfigPath());
    }

    protected function fillConfigPath(): string
    {
        $psr4 = [];
        if (file_exists($this->configsHolder->projectRootDir . self::AUTOLOAD_PSR4)) {
            $psr4 = include $this->configsHolder->projectRootDir . self::AUTOLOAD_PSR4;
        }
        return ($psr4[$this->configsHolder->namespace . '\\'] ?? [])[0] ?? $this->configsHolder->projectRootDir;
    }

    protected function getRpcTransport(array $transportConfig): string
    {
        try {
            return str_replace(
                '{user}:{pass}',
                AsyncTransport::PLACEHOLDER,
                (string)RpcTransport::fromArray($transportConfig)
            );
        } catch (Throwable) {
            return '';
        }
    }

    public function make(?callable $callbackOutput = null): void
    {
        $configs = $this->sdkConfigs->getConfigs(true);
        $vendor = $this->configsHolder->apiVendorAlias;

        $server = current($this->configsHolder->rpcResponse['servers']);
        foreach ($server[EnumResolver::CORE]['transport'] ?? [] as $transportName => $transportConfig) {
            $configs[$vendor][$transportName] = $this->getRpcTransport($transportConfig);
        }

        if (empty($configs[$vendor])) {
            $configs[$vendor] = [
                SdkConfigs::SYNC => $this->configsHolder->apiUrl
            ];
        }

        file_put_contents($this->sdkConfigs->getConfigDistPath(), Yaml::dump($configs));
    }

}