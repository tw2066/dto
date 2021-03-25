<?php

namespace Tang\DTO;

use JsonMapper;

class Mapper
{

    private JsonMapper $jsonMapper;

    public function __construct()
    {
        $this->jsonMapper = new JsonMapper();
        $this->jsonMapper->bEnforceMapType = false;
    }

    public function map($json, $object)
    {
        return $this->jsonMapper->map($json, $object);
    }

}