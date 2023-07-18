<?php

use Ufo\RpcSdk\Maker\Maker;

require_once __DIR__ . '/../vendor/autoload.php';

$vendorName = readline('Enter API vendor name: ');
$apiUrl = readline('Enter the API url: ');

$maker = new Maker($apiUrl, $vendorName);
$maker->make();
