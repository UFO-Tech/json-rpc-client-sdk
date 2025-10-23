<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\Definitions\Configs\ParamConfig;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeDefinition;
use Ufo\RpcSdk\Maker\Traits\ClassDefinitionsMethodsHolderTrait;

use function end;
use function explode;

class DtoClassDefinition implements IClassLikeDefinition
{
    use ClassDefinitionsMethodsHolderTrait;

    const string TYPE_CLASS = 'DTO';

    const string FOLDER = 'DTO';

    protected array $docs = [];

    /**
     * @param string $namespace
     * @param string $className
     */
    public function __construct(
        string $namespace,
        string $className,
        protected ConfigsHolder $configsHolder
    )
    {
        $this->className = $className;
        $this->namespace = $namespace;
    }

    public function getDocs(): array
    {
        return $this->docs;
    }

    /**
     * @param ParamConfig[] $properties
     */
    public function setProperties(array $properties): void
    {
        foreach ($properties as $paramConfig) {
            $name = $paramConfig->name;
            if ($paramConfig->typeConfig->typeDoc !== $paramConfig->typeConfig->type) {
                $this->docs[$name] = $paramConfig->typeConfig->typeDoc;
            }
            $this->properties[$name] = $paramConfig->typeConfig->type;

            if (!$paramConfig->required) {
                $this->addDefaultValue(
                    $name,
                    $this->configsHolder->getDefaultValueForParam($this->className, $name) ?? $paramConfig->defaultValue
                );
            }
        }

    }
}
