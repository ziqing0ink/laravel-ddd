<?php

namespace {{namespace}};

use ziqing\ddd\Entity;
use ziqing\ddd\Factory;
use {{entityFullClassName}};

/**
 * Class {{className}}
 * @package {{package}}
 * @internal
 *
 * 用于构造实体 {{entityClassName}} 的类实例
 *
 */
class {{className}} extends Factory
{
    /**
     * you can inject your dependency here
     * {{className}} constructor.
     */
    public function __construct()
    {
    }

    public function init()
    {
        //you can init something here
    }

    /**
     * @return {{entityClassName}}
     */
    public function buildOne(): Entity
    {
        //todo: write your building logic here
    }

    /**
     * @return {{entityClassName}}[]
     */
    public function buildMany(): array
    {
        //todo: write your building logic here
    }
}
