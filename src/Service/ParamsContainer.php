<?php

namespace Daz\OptimaClass\Service;

class ParamsContainer
{
    public $params = [];

    public function __construct(array $params = [])
    {
        $this->params = $params;
    }
}