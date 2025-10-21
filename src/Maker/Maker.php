<?php

namespace Ufo\RpcSdk\Maker;

use Exception;
use Symfony\Bundle\MakerBundle\Generator;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeDefinition;
use Ufo\RpcSdk\Maker\Interfaces\IMaker;

class Maker
{
    protected array $classes = [];

    /**
     * @param ConfigsHolder $configsHolder
     * @param Generator $generator
     * @param IMaker[] $makers
     */
    public function __construct(
        readonly public ConfigsHolder $configsHolder,
        protected Generator $generator,
        protected array $makers = [],
    ) {}

    /**
     * @param callable|null $callbackOutput
     * @return void
     * @throws Exception
     */
    public function make(?callable $callbackOutput = null): void
    {
        foreach ($this->makers as $maker) {
            $maker->make(
                function (IClassLikeDefinition $classDefinition) use ($callbackOutput) {
                    $classDefinition = $callbackOutput($classDefinition);
                    $this->classes[$classDefinition::TYPE_CLASS][$classDefinition->getShortName()] = $classDefinition;
                }
            );
        }
    }

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function __destruct()
    {
        $this->generator->writeChanges();
    }
}
