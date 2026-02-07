<?php

namespace Sonata\Framework\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public string $path,
        public string $method = 'GET',

        //Наименование в документации
        public ?string $summary = null,

        //Описание метода для документации
        public ?string $description = null
    ) {}
}