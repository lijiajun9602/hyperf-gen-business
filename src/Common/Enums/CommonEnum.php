<?php

namespace Hyperf\GenBusiness\Common\Enums;

use Lishun\Enums\Annotations\EnumCodePrefix;
use Lishun\Enums\Interfaces\EnumCodeInterface;
use Lishun\Enums\Traits\EnumCodeGet;

#[EnumCodePrefix(null, '公用枚举')]
enum CommonEnum: int implements EnumCodeInterface
{
    use EnumCodeGet;

    public const SUCCESS = 1;

    public const FAIL = 2;
}
