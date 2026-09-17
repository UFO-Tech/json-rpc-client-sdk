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
use function sprintf;
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

    protected function getRpcTransport(array $transportConfig, string $transportName): string
    {
        return str_replace(
            '{user}:{pass}',
            AsyncTransport::getSecretPlaceholder($transportName),
            (string)RpcTransport::fromArray($transportConfig)
        );
    }

    public function make(?callable $callbackOutput = null): void
    {
        $configs = $this->sdkConfigs->getConfigs(true);
        $vendor = $this->configsHolder->apiVendorAlias;

        foreach ($this->configsHolder->getTransports() as $transportName => $transportConfig) {
            try {
                $configs[$vendor][$transportName] = $this->getRpcTransport($transportConfig, $transportName);
            } catch (Throwable) {
                continue;
            }
        }

        if (empty($configs[$vendor])) {
            $configs[$vendor][RpcTransport::SYNC_PREFIX] = $this->configsHolder->apiUrl;
        }

        file_put_contents($this->sdkConfigs->getConfigDistPath(), Yaml::dump($configs));
    }

}