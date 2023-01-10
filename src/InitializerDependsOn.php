<?php

namespace NovaTech\Initializer;


interface InitializerDependsOn
{
    /**
     * This method must return an array of fixtures classes
     * on which the implementing class depends on
     *
     * @psalm-return array<class-string<Initializer>>
     */
    public function getDependencies();
}