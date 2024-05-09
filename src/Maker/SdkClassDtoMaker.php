<?php

namespace Ufo\RpcSdk\Maker;

use Ufo\RpcSdk\Maker\Definitions\ClassDefinition;

class SdkClassDtoMaker
{
    const DEFAULT_TEMPLATE = __DIR__ . '/../../templates/dto.php.twig';

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
                ],
                'interfaces' => [],
                'extends' => '',
                'methods' => $this->classDefinition->getMethods(),
                'properties' => $this->classDefinition->getProperties(),
                'tab' => function (int $count = 1) {
                    return str_repeat(' ', 4 * $count);
                }
            ]
        );

        $generator->writeChanges();
    }

    public static function generateName(string $procedure): string
    {
        $procedureParts = explode('.', $procedure);
        $name = str_replace('Procedure', '', $procedureParts[0]);

        if (isset($procedureParts[1])) {
            $name .= ucfirst($procedureParts[1]);
        }

        return $name . "DTO";
    }

    protected function getTemplateName(): string
    {
        return 'procedure';
    }
}
