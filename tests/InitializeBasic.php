<?php

namespace NovaTech\Tests\Initializer;

use NovaTech\Initializer\Initializer;
use NovaTech\Initializer\InitializerGroupInterface;

class InitializeBasic extends Initializer implements InitializerGroupInterface{


    public function initialize(): void
    {

    }


    public function getGroups(): array
    {
        return ['test'];
    }
}