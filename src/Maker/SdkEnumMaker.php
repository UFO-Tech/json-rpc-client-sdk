<?php

namespace Ufo\RpcSdk\Maker;

use Exception;
use Ufo\RpcSdk\Maker\Definitions\EnumDefinition;

class SdkEnumMaker
{
    const string DEFAULT_TEMPLATE = __DIR__.'/../../templates/enum.php.twig';

    protected string $template = self::DEFAULT_TEMPLATE;

    public function __construct(
        protected Maker  $maker,
        protected EnumDefinition $classDefinition
    ) {}

    /**
     * @throws Exception
     */
    public function generate(): void
    {
        $generator = $this->maker->getGenerator();
        $generator->generateClass(
            $this->classDefinition->getFullName(),
            $this->template,
            [
                'class_name' => $this->classDefinition->getEnumName(),
                'enum_type' => $this->classDefinition->getType(),
                'vendor' => [
                    'name' => $this->maker->getApiVendorAlias(),
                    'url' => $this->maker->getApiUrl(),
                ],
                'uses' => [
                ],
                'interfaces' => [],
                'cases' => $this->classDefinition->getCases(),
                'tab' => function (int $count = 1) {
                    return str_repeat(' ', 4 * $count);
                }
            ]
        );

        $generator->writeChanges();
    }

}
