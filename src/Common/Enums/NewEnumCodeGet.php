<?php

namespace App\Common\Enums;

use Lishun\Enums\Traits\EnumCodeGet;

trait NewEnumCodeGet
{
    use EnumCodeGet;

    public function getKeyCode($key): ?int
    {
        return self::getEnums()[$key]['code'] ?? null;
    }


    public function getKeyMsg($key): ?int
    {
        return self::getEnums()[$key]['mag'] ?? null;
    }
}