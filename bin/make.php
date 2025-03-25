<?php

use Ufo\RpcSdk\Maker\Definitions\ClassDefinition;
use Ufo\RpcSdk\Maker\Maker;
use UfoCms\ColoredCli\CliColor;

require_once __DIR__ . '/../vendor/autoload.php';

echo CliColor::YELLOW->value;
$vendorName = 'msg';
$apiUrl = 'https://dev.msg.trademaster.in.ua/api';
//$vendorName = readline('Enter API vendor name: ');
//$apiUrl = readline('Enter the API url: ');

echo CliColor::RESET->value;

try {
    $maker = new Maker(
        apiUrl: $apiUrl,
        apiVendorAlias: $vendorName,
        namespace: Maker::DEFAULT_NAMESPACE, // 'Ufo\RpcSdk\Client'
        projectRootDir: getcwd(), // project_dir
        cacheLifeTimeSecond: Maker::DEFAULT_CACHE_LIFETIME, // 3600
        urlInAttr: false
    );

    echo CliColor::GREEN->value . "Start generate SDK for '$vendorName' ($apiUrl)" . CliColor::RESET->value . PHP_EOL;

    $maker->make(function (ClassDefinition $classDefinition) {
        echo 'Create class: ' . CliColor::LIGHT_BLUE->value . $classDefinition->getFullName() . CliColor::RESET->value
             .PHP_EOL;
        echo 'Methods: ' . PHP_EOL;
        foreach ($classDefinition->getMethods() as $method) {
            echo CliColor::CYAN->value
                . $method->getName()
                . '(' . $method->getArgumentsSignature() . ')'
                . (!empty($method->getReturns()) ? ':' : '')
                . implode('|', $method->getReturns())
                . CliColor::RESET->value
                . PHP_EOL;
        }
        echo str_repeat('=', 20) . PHP_EOL;
    });
    echo CliColor::GREEN->value . "Generate SDK is complete" . CliColor::RESET->value . PHP_EOL;
} catch (Throwable $e) {
    echo CliColor::RED->value . "Error: " . CliColor::RESET->value;
    echo $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ': ' .$e->getLine() . PHP_EOL;
}

