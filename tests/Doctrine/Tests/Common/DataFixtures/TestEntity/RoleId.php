<?php

namespace Doctrine\Tests\Common\DataFixtures\TestEntity;

final class RoleId
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getId()
    {
        return $this->value;
    }
}

