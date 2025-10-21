<?php

namespace Ufo\RpcSdk\Maker;


use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\Yaml\Yaml;
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
        $psr4 = require($this->configsHolder->projectRootDir . self::AUTOLOAD_PSR4);
        return ($psr4[$this->configsHolder->namespace . '\\'] ?? [])[0] ?? $this->configsHolder->projectRootDir;
    }

    protected function getRpcTransport(bool $async = false): string
    {
        $type = $async ? 'async' : 'sync';
        try {
            return str_replace(
                '{user}:{pass}',
                AsyncTransport::PLACEHOLDER,
                (string)RpcTransport::fromArray(
                    current($this->configsHolder->rpcResponse['servers'])[EnumResolver::CORE]['transport'][$type] ?? []
                )
            );
        } catch (\Throwable) {
            return '';
        }
    }

    public function make(?callable $callbackOutput = null): void
    {
        $configs = $this->sdkConfigs->getConfigs(true);
        $vendor = $this->configsHolder->apiVendorAlias;
        $configs[$vendor] = [
            SdkConfigs::SYNC => $this->configsHolder->apiUrl
        ];
        $async = $this->getRpcTransport(true);
        if (!empty($async)) {
            $configs[$vendor][SdkConfigs::ASYNC] = $async;
        }

        file_put_contents($this->sdkConfigs->getConfigDistPath(), Yaml::dump($configs));
    }

}