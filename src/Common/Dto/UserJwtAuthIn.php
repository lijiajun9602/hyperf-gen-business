<?php

namespace Hyperf\GenBusiness\Common\Dto;

use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\PhpAccessor\Annotation\HyperfData;
use PhpAccessor\Attribute\Data;

#[HyperfData]
#[Data]
class UserJwtAuthIn
{
    #[ApiModelProperty(value: '用户ID Token获得', hidden: true)]
    public int $userId;
}