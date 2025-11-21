<?php

use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;
use Symfony\Bundle\MakerBundle\Util\ComposerAutoloaderFinder;
use Symfony\Bundle\MakerBundle\Util\MakerFileLinkFormatter;
use Symfony\Component\Filesystem\Filesystem;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\DocReader\FileReader;
use Ufo\RpcSdk\Maker\DocReader\HttpReader;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeDefinition;
use Ufo\RpcSdk\Maker\Interfaces\IHaveMethodsDefinitions;
use Ufo\RpcSdk\Maker\Maker;
use Ufo\RpcSdk\Maker\SdkConfigMaker;
use Ufo\RpcSdk\Maker\SdkDtoMaker;
use Ufo\RpcSdk\Maker\SdkEnumMaker;
use Ufo\RpcSdk\Maker\SdkProcedureMaker;
use UfoCms\ColoredCli\CliColor;

require_once __DIR__ . '/../vendor/autoload.php';

echo CliColor::YELLOW->value;

$vendorName = 'q';//readline('Enter API vendor name: ');
//$vendorName = readline('Enter API vendor name: ');
$apiUrl = '';//readline('Enter the API url: ');

echo CliColor::RESET->value;

try {

//    $docReader = new HttpReader($apiUrl);
    $docReader = new FileReader(__DIR__.'/schema.json');


    $configHolder = new ConfigsHolder(
        $docReader,
        projectRootDir: getcwd(), // project_dir
        apiVendorAlias: $vendorName,
        namespace: ConfigsHolder::DEFAULT_NAMESPACE, // 'Ufo\RpcSdk\Client'
        urlInAttr: false,
        cacheLifeTimeSecond: 1 //ConfigsHolder::DEFAULT_CACHE_LIFETIME,
    );
    $generator = new Generator(
        new FileManager(
            new Filesystem(),
            new AutoloaderUtil(
                new ComposerAutoloaderFinder($configHolder->namespace)
            ),
            new MakerFileLinkFormatter(),
            $configHolder->projectRootDir
        ),
        $configHolder->namespace
    );

    $maker = new Maker (
        configsHolder: $configHolder,
        generator: $generator,
        makers: [
            new SdkEnumMaker($configHolder, $generator),
            new SdkDtoMaker($configHolder, $generator),
            new SdkProcedureMaker($configHolder, $generator),
            new SdkConfigMaker($configHolder, $generator),
        ]
    );

    echo CliColor::GREEN->value . "Start generate SDK for '$vendorName' ($apiUrl)" . CliColor::RESET->value . PHP_EOL;

    $maker->make(function (IClassLikeDefinition $classDefinition) {
        echo 'Create class: ' . CliColor::LIGHT_BLUE->value . $classDefinition->getFQCN() . CliColor::RESET->value
             . PHP_EOL;
        if (count($classDefinition->getProperties()) > 0) {
            foreach ($classDefinition->getProperties() as $name => $property) {
                echo CliColor::CYAN->value
                     . 'public ' . $property . ' $' . $name . ($classDefinition->getDefaultValues()[$name] ?? '')
                     . PHP_EOL;
            }
        }
        if ($classDefinition instanceof IHaveMethodsDefinitions) {
            foreach ($classDefinition->getMethods() as $method) {
                echo CliColor::CYAN->value
                     . $method->getName()
                     . '(' . $method->getArgumentsSignature() . ')'
                     . (!empty($method->getReturns()) ? ':' : '')
                     . $method->getReturns()
                     . CliColor::RESET->value
                     . PHP_EOL;
            }
        }
        echo str_repeat('=', 20) . PHP_EOL;
        return $classDefinition;
    });
    echo CliColor::GREEN->value . "Generate SDK is complete" . CliColor::RESET->value . PHP_EOL;
} catch (Throwable $e) {
    echo CliColor::RED->value . "Error: " . CliColor::RESET->value;
    echo $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ': ' .$e->getLine() . PHP_EOL;
}

