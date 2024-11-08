<?php

namespace App\Common\Dto;

use Hyperf\ApiDocs\Annotation\ApiModelProperty;

class MetaClass
{
    #[ApiModelProperty("当前页数")]
    public int $page;
    #[ApiModelProperty("每页数量")]
    public int $perPage;
    #[ApiModelProperty("总数")]
    public int $total;
    #[ApiModelProperty("是否有分页")]
    public bool $hasPages;


}