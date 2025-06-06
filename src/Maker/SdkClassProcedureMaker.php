<?php

namespace Ufo\RpcSdk\Maker;

use Exception;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\RpcObject\Transformer\Transformer;
use Ufo\RpcSdk\Interfaces\ISdkMethodClass;
use Ufo\RpcSdk\Maker\Definitions\ClassDefinition;
use Ufo\RpcSdk\Procedures\AbstractAsyncProcedure;
use Ufo\RpcSdk\Procedures\AbstractProcedure;
use Ufo\RpcSdk\Procedures\ApiMethod;

class SdkClassProcedureMaker
{
    const string SDK_PROCEDURE_INTERFACE = ISdkMethodClass::class;
    const string DEFAULT_TEMPLATE = __DIR__.'/../../templates/procedure.php.twig';

    protected string $template = self::DEFAULT_TEMPLATE;

    public function __construct(
        protected Maker  $maker,
        protected ClassDefinition $classDefinition,
        protected bool $apiUrlInAttr = true
    ) {}

    /**
     * @throws Exception
     */
    public function generate(): void
    {
        $generator = $this->maker->getGenerator();
        $interface = explode('\\', static::SDK_PROCEDURE_INTERFACE);
        $generator->generateClass(
            $this->classDefinition->getFullName(),
            $this->template,
            [
                'class_name' => $this->classDefinition->getClassName(),
                'vendor' => [
                    'name' => $this->maker->getApiVendorAlias(),
                    'url' => $this->maker->getApiUrl(),
                ],
                'async' => $this->classDefinition->async,
                'apiUrlInAttr' => $this->apiUrlInAttr,
                'uses' => [
                    static::SDK_PROCEDURE_INTERFACE,
                    AbstractProcedure::class,
                    AbstractAsyncProcedure::class,
                    ApiMethod::class,
                    AutoconfigureTag::class,
                    Transformer::class,
                    'Symfony\Component\Validator\Constraints as Assert',

                    ...$this->classDefinition->getMethodsUses()
                ],
                'response'=>'RpcResponse',
                'interfaces' => [end($interface)],
                'extends' =>  $this->classDefinition->async ? 'AbstractAsyncProcedure' : 'AbstractProcedure',
                'methods' => $this->classDefinition->getMethods(),
                'properties' => [],
                'tab' => function (int $count = 1) {
                    return str_repeat(' ', 4 * $count);
                }
            ]
        );

        $generator->writeChanges();
    }
}
