<?php

namespace NovaTech\Tests\Initializer;

use NovaTech\Initializer\Initializer;
use NovaTech\Initializer\InitializerDependsOn;
use NovaTech\Initializer\InitializerGroupInterface;

class InitializeWithDependecy  extends Initializer implements InitializerDependsOn, InitializerGroupInterface
{

    public function initialize(): void
    {

    }

    public function getGroups(): array
    {
        return ['test'];
    }

    public function getDependencies()
    {
        return [
            InitializeBasic::class
        ];
    }
}