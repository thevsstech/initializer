<?php

namespace NovaTech\Initializer;

abstract class Initializer
{
    public array $payload = [];
    abstract public function initialize(): void;
}