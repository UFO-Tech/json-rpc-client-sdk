<?php

namespace Ufo\RpcSdk\Maker\Interfaces;

interface IClassLikeDefinition
{
    const string TYPE_CLASS = 'class';

    public function getNamespace(): string;

    public function getShortName(): string;

    public function getFQCN(): string;

    public function getProperties(): array;

    public function setProperties(array $properties): void;
}