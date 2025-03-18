<?php

namespace Hyperf\GenBusiness\Common\Dto;



use Hyperf\ApiDocs\Annotation\ApiModel;
use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\DTO\Annotation\Dto;

#[Dto]
#[ApiModel(value: 'UserJwtAuthIn入参')]
class UserJwtAuthIn
{
    #[ApiModelProperty(value: '用户ID Token获得', hidden: true)]
    public ?int $userId;
    #[ApiModelProperty(value: '用户昵称', hidden: true)]
    public ?string $nickName;

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * @param int $userId
     */
    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * @return string
     */
    public function getNickName(): string
    {
        return $this->nickName;
    }

    /**
     * @param string $nickName
     */
    public function setNickName(string $nickName): void
    {
        $this->nickName = $nickName;
    }

}
