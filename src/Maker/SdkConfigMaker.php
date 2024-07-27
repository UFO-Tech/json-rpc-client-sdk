<?php

namespace Ufo\RpcSdk\Maker;


use Symfony\Component\Yaml\Yaml;
use Ufo\RpcSdk\Procedures\SdkConfigs;

class SdkConfigMaker
{
    const string AUTOLOAD_PSR4 = '/vendor/composer/autoload_psr4.php';

    protected SdkConfigs $sdkConfigs;

    public function __construct(
        protected Maker $maker,
    )
    {
        $this->sdkConfigs = new SdkConfigs($this->fillConfigPath());
    }

    public function generate(): void
    {
        $configs = $this->sdkConfigs->getConfigs(true);
        $configs[$this->maker->getApiVendorAlias()] = $this->maker->getApiUrl();
        file_put_contents($this->sdkConfigs->getConfigDistPath(), Yaml::dump($configs));
    }

    protected function fillConfigPath(): string
    {
        $psr4 = require($this->maker->getProjectRootDir() . self::AUTOLOAD_PSR4);
        return ($psr4[$this->maker->namespace . '\\'] ?? [])[0] ?? $this->maker->getProjectRootDir();
    }


}