<?php

namespace NovaTech\Tests\Initializer;

use NovaTech\Initializer\Initializer;
use NovaTech\Initializer\InitializerDependsOn;
use NovaTech\Initializer\InitializerGroupInterface;

class InitializeGroupAdvanced extends Initializer implements InitializerDependsOn, InitializerGroupInterface {


    public function initialize(): void
    {

    }


    public function getGroups(): array
    {
        return ['test', 'basic'];
    }

    public function getDependencies()
    {
        return [
            InitializeWithDependecy::class
        ];
    }
}