<?php

namespace Ufo\RpcSdk\Maker;

use Ufo\RpcObject\RpcResponse;
use Ufo\RpcSdk\Interfaces\ISdkMethodClass;
use Ufo\RpcSdk\Maker\Definitions\ClassDefinition;
use Ufo\RpcSdk\Procedures\AbstractProcedure;
use Ufo\RpcSdk\Procedures\ApiMethod;
use Ufo\RpcSdk\Procedures\ApiUrl;

class SdkClassProcedureMaker
{
    const SDK_PROCEDURE_INTERFACE = ISdkMethodClass::class;
    const DEFAULT_TEMPLATE = __DIR__ . '/../../templates/procedure.php.twig';

    protected string $template = self::DEFAULT_TEMPLATE;

    public function __construct(
        protected Maker  $maker,
        protected ClassDefinition $classDefinition
    )
    {
    }

    /**
     * @throws \Exception
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
                'uses' => [
                    static::SDK_PROCEDURE_INTERFACE,
                    AbstractProcedure::class,
                    ApiMethod::class,
                    ApiUrl::class,
                    RpcResponse::class
                ],
                'response'=>'RpcResponse',
                'interfaces' => [end($interface)],
                'extends' => 'AbstractProcedure',
                'methods' => $this->classDefinition->getMethods(),
                'tab' => function (int $count = 1) {
                    return str_repeat(' ', 4 * $count);
                }
            ]
        );

        $generator->writeChanges();
    }


    protected function getTemplateName(): string
    {
        return 'procedure';
    }
}
