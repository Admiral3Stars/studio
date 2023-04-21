<?php

class ArrayLimit{

    private array $array;
    private const LIMIT = 5;

    public function __construct (string $list)
    {

        $listArray = explode(',', $list);

        for($i = 0; $i < self::LIMIT; $i++){
            $this->array[$i] = $listArray[$i];
        }

    }

    public function getArray()
    {
        return $this->array;
    }
}

$anyList = 'mikky, mouse, bb2, gollow, singl, robert';

$anyObj = new ArrayLimit($anyList);
var_dump($anyObj->getArray());