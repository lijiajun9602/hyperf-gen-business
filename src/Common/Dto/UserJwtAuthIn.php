<?php

namespace Hyperf\GenBusiness\Common\Dto;



use Hyperf\ApiDocs\Annotation\ApiModel;
use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\DTO\Annotation\Dto;
use Hyperf\PhpAccessor\Annotation\HyperfData;
use PhpAccessor\Attribute\Data;

#[HyperfData]
#[Data]
#[Dto]
#[ApiModel(value: 'UserJwtAuthIn入参')]
class UserJwtAuthIn
{
    #[ApiModelProperty(value: '用户ID Token获得', hidden: true)]
    public int $userId;
    #[ApiModelProperty(value: '用户昵称', hidden: true)]
    public string $nickName;
}