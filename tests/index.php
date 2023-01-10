<?php


include "../vendor/autoload.php";


$initializer = new \NovaTech\Initializer\InitializationManager([
    \NovaTech\Tests\Initializer\InitializeBasic::class,
    \NovaTech\Tests\Initializer\InitializeWithDependecy::class,
    \NovaTech\Tests\Initializer\InitializeGroupBasic::class,
    \NovaTech\Tests\Initializer\InitializeGroupAdvanced::class
]);




foreach ($initializer->run(['test']) as $event){
    print_r($event);
}