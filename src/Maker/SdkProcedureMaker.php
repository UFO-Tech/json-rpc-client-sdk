<?php

namespace Ufo\RpcSdk\Maker;

use Deprecated;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\RpcError\RpcDataNotFoundException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcSdk\Exceptions\SdkBuilderException;
use Ufo\RpcSdk\Interfaces\ISdkMethodClass;
use Ufo\RpcSdk\Maker\Definitions\ArgumentDefinition;
use Ufo\RpcSdk\Maker\Definitions\ClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\Definitions\Configs\ProcedureConfig;
use Ufo\RpcSdk\Maker\Definitions\MethodDefinition;
use Ufo\RpcSdk\Maker\Helpers\ClassHelper;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeStackHolder;
use Ufo\RpcSdk\Maker\Interfaces\IMaker;
use Ufo\RpcSdk\Maker\Traits\ClassLikeStackHolderTrait;
use Ufo\RpcSdk\Maker\Traits\ProcessCallbackTrait;
use Ufo\RpcSdk\Procedures\AbstractAsyncProcedure;
use Ufo\RpcSdk\Procedures\AbstractProcedure;
use Ufo\RpcSdk\Procedures\ApiMethod;

use function end;
use function explode;
use function str_repeat;

/**
 * @property ClassDefinition[] $classStack
 */
class SdkProcedureMaker implements IMaker, IClassLikeStackHolder
{
    use ProcessCallbackTrait, ClassLikeStackHolderTrait;
    const string SDK_PROCEDURE_INTERFACE = ISdkMethodClass::class;
    const string DEFAULT_TEMPLATE = __DIR__.'/../../templates/procedure.php.twig';

    protected string $template = self::DEFAULT_TEMPLATE;

    public function __construct(
        readonly public ConfigsHolder $configsHolder,
        protected Generator $generator,
    ) {}

    /**
     * @throws WrongWayException
     * @throws SdkBuilderException
     * @throws RpcDataNotFoundException
     */
    protected function prepareData($prepareData = [], bool $async = false): array
    {
        foreach ($this->configsHolder->getRpcProcedures() as $procedureName => $procedureData) {
            if ($this->configsHolder->methodShouldIgnore($procedureName, $async)) continue;

            $classDefinition = $this->classAddOrUpdate($procedureName, $procedureData, $async);
            $prepareData[$classDefinition->getShortName()] = $classDefinition;
        }
        if (!$async && !empty($this->configsHolder->getRpcTransport(true))) {
            $prepareData = $this->prepareData($prepareData, true);
        }
        return $prepareData;
    }

    /**
     * @param callable|null $callbackOutput
     * @return void
     * @throws RpcDataNotFoundException
     * @throws SdkBuilderException
     * @throws WrongWayException
     */
    public function make(?callable $callbackOutput = null): void
    {
        foreach ($this->prepareData() as $className => $classDefinition) {
            $this->generateClass($classDefinition, $callbackOutput);
        }
    }

    protected function generateClass(ClassDefinition $classDefinition, ?callable $callbackOutput = null): void
    {
        if ($callbackOutput) {
            $callbackOutput($classDefinition);
        }

        $interface = explode('\\', static::SDK_PROCEDURE_INTERFACE);
        $this->generator->generateClass(
            $classDefinition->getFQCN(),
            $this->template,
            [
                'class_name' => $classDefinition->getShortName(),
                'vendor' => [
                    'name' => $this->configsHolder->apiVendorAlias,
                    'url' => $this->configsHolder->apiUrl,
                ],
                'async' => $classDefinition->async,
                'apiUrlInAttr' => $this->configsHolder->urlInAttr,
                'uses' => [
                    static::SDK_PROCEDURE_INTERFACE,
                    AbstractProcedure::class,
                    AbstractAsyncProcedure::class,
                    ApiMethod::class,
                    AutoconfigureTag::class,
                    Deprecated::class,
                    'Symfony\Component\Validator\Constraints as Assert',

                    ...$classDefinition->getMethodsUses()
                ],
                'response'=>'RpcResponse',
                'interfaces' => [end($interface)],
                'extends' =>  $classDefinition->async ? 'AbstractAsyncProcedure' : 'AbstractProcedure',
                'methods' => $classDefinition->getMethods(),
                'properties' => [],
                'tab' => function (int $count = 1) {
                    return str_repeat(' ', 4 * $count);
                }
            ]
        );

    }

    /**
     * @throws WrongWayException
     * @throws SdkBuilderException
     * @throws RpcDataNotFoundException
     */
    protected function classAddOrUpdate(string $procedureName, ProcedureConfig $procedureData, bool $async = false): ClassDefinition
    {
        $ns = $this->configsHolder->namespace;
        $ns .= '\\' . $this->configsHolder->apiVendorAlias;

        $convertor = ClassHelper::convertMethodToClassname($procedureName, $async);

        try {
            $procedureDefinition = $this->getFromStack($convertor->className);
        } catch (SdkBuilderException) {
            $procedureDefinition = new ClassDefinition($ns, $convertor->className, $async);
            $this->addToStack($procedureDefinition);
        }
        ClassHelper::removePreviousClass($procedureDefinition->getFQCN());

        $method = new MethodDefinition($convertor->apiMethod, $procedureName, $procedureData->deprecated);

        $method->setReturns($procedureData->result);

        $procedureDefinition->addMethod($method);

        foreach ($procedureData->params as $paramConfig) {
            $argument = new ArgumentDefinition(
                name: $paramConfig->name,
                typeConfig: $paramConfig->typeConfig,
                optional: !$paramConfig->required,
                assertions: $paramConfig->assertions ?? null,
                defaultValue: $this->configsHolder->getDefaultValueForParam($paramConfig->parentConfig->name, $paramConfig->name)
            );
            $method->addArgument($argument);
        }

        return $procedureDefinition;
    }
}
